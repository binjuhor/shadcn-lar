<?php

namespace Modules\Finance\Services;

use Google\ApiCore\ApiException;
use Google\Cloud\AIPlatform\V1\Blob;
use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\Content;
use Google\Cloud\AIPlatform\V1\GenerateContentRequest;
use Google\Cloud\AIPlatform\V1\GenerationConfig;
use Google\Cloud\AIPlatform\V1\Part;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over google/cloud-ai-platform SDK for Gemini generateContent calls.
 *
 * Why the SDK over bare REST: Google's GFE applies stricter abuse filtering to clients
 * that don't send the canonical x-goog-api-client / User-Agent identifiers. The SDK
 * sends them automatically, so calls are far less likely to hit the 417 "Sorry..."
 * interstitial on shared/datacenter egress IPs.
 *
 * Transport: forces REST to avoid requiring the grpc PHP extension on Alpine. The SDK
 * falls back to REST anyway when grpc isn't available, but pinning it makes behavior
 * deterministic across local/prod environments.
 */
class VertexGeminiClient
{
    protected ?PredictionServiceClient $client = null;

    public function __construct(
        protected string $project,
        protected string $region,
        protected string $modelName,
    ) {}

    /**
     * Send a generateContent request. Accepts the same payload shape the REST path
     * uses (`['contents' => [...], 'generationConfig' => [...]]`) and returns a
     * REST-shaped response so existing parsers keep working untouched.
     *
     * @return array{candidates?: array<int, array{content: array{parts: array<int, array{text?: string}>}}>, error?: string, status?: int}
     */
    public function generateContent(array $payload): array
    {
        try {
            $client = $this->getClient();

            $request = (new GenerateContentRequest())
                ->setModel($this->buildModelPath())
                ->setContents($this->buildContents($payload['contents'] ?? []));

            if (! empty($payload['generationConfig'])) {
                $request->setGenerationConfig($this->buildGenerationConfig($payload['generationConfig']));
            }

            $response = $client->generateContent($request);

            return $this->responseToArray($response);
        } catch (ApiException $e) {
            $status = $this->mapStatusCode($e->getStatus());

            Log::error('Vertex SDK error', [
                'backend' => 'vertex_sdk',
                'model' => $this->modelName,
                'status' => $status,
                'grpc_status' => $e->getStatus(),
                'message' => $e->getMessage(),
                'metadata' => $e->getMetadata(),
            ]);

            return [
                'error' => $this->humanError($status, $e->getMessage()),
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            Log::error('Vertex SDK exception', [
                'backend' => 'vertex_sdk',
                'model' => $this->modelName,
                'message' => $e->getMessage(),
            ]);

            return [
                'error' => 'Could not reach AI service: '.$e->getMessage(),
                'status' => 0,
            ];
        }
    }

    protected function getClient(): PredictionServiceClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        // Each region has its own apiEndpoint; pinning prevents the SDK from defaulting
        // to us-central1 when other regions are configured.
        $this->client = new PredictionServiceClient([
            'apiEndpoint' => "{$this->region}-aiplatform.googleapis.com",
            // Force REST so we don't need the grpc PHP extension on Alpine.
            'transport' => 'rest',
        ]);

        return $this->client;
    }

    protected function buildModelPath(): string
    {
        return sprintf(
            'projects/%s/locations/%s/publishers/google/models/%s',
            $this->project,
            $this->region,
            $this->modelName,
        );
    }

    /**
     * @param  array<int, array{role?: string, parts: array<int, array<string, mixed>>}>  $contents
     * @return Content[]
     */
    protected function buildContents(array $contents): array
    {
        $out = [];
        foreach ($contents as $entry) {
            $content = new Content();
            $content->setRole($entry['role'] ?? 'user');
            $content->setParts($this->buildParts($entry['parts'] ?? []));
            $out[] = $content;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @return Part[]
     */
    protected function buildParts(array $parts): array
    {
        $out = [];
        foreach ($parts as $part) {
            $p = new Part();

            if (isset($part['text'])) {
                $p->setText($part['text']);
            } elseif (isset($part['inline_data'])) {
                $blob = new Blob();
                $blob->setMimeType($part['inline_data']['mime_type'] ?? 'application/octet-stream');
                // SDK expects raw bytes; our payload uses base64 (REST format), so decode.
                $blob->setData(base64_decode($part['inline_data']['data'] ?? '', true) ?: '');
                $p->setInlineData($blob);
            }

            $out[] = $p;
        }

        return $out;
    }

    protected function buildGenerationConfig(array $cfg): GenerationConfig
    {
        $gc = new GenerationConfig();
        if (isset($cfg['temperature'])) {
            $gc->setTemperature((float) $cfg['temperature']);
        }
        if (isset($cfg['topP'])) {
            $gc->setTopP((float) $cfg['topP']);
        }
        if (isset($cfg['maxOutputTokens'])) {
            $gc->setMaxOutputTokens((int) $cfg['maxOutputTokens']);
        }

        return $gc;
    }

    /**
     * Convert the protobuf response back to the same array shape the bare-REST path returns,
     * so the parser's parseResponse() keeps working unchanged.
     */
    protected function responseToArray(\Google\Cloud\AIPlatform\V1\GenerateContentResponse $response): array
    {
        $candidates = [];
        foreach ($response->getCandidates() as $candidate) {
            $parts = [];
            $content = $candidate->getContent();
            if ($content) {
                foreach ($content->getParts() as $part) {
                    $parts[] = ['text' => $part->getText()];
                }
            }
            $candidates[] = [
                'content' => [
                    'parts' => $parts,
                ],
            ];
        }

        return ['candidates' => $candidates];
    }

    /**
     * Map gRPC-style status codes to HTTP-equivalent codes the parser already handles.
     */
    protected function mapStatusCode(string $grpcStatus): int
    {
        return match (strtoupper($grpcStatus)) {
            'NOT_FOUND' => 404,
            'INVALID_ARGUMENT', 'FAILED_PRECONDITION' => 400,
            'PERMISSION_DENIED' => 403,
            'UNAUTHENTICATED' => 401,
            'RESOURCE_EXHAUSTED' => 429,
            'DEADLINE_EXCEEDED' => 504,
            'UNAVAILABLE' => 503,
            'INTERNAL' => 500,
            default => 500,
        };
    }

    protected function humanError(int $status, string $message): string
    {
        // Google's GFE occasionally serves the "Sorry... automated queries" HTML interstitial
        // wrapped as FAILED_PRECONDITION (status 400). The body is HTML, not a real API error,
        // so surface a clean retryable message instead of leaking the markup to users.
        if ($status === 400 && str_contains($message, '<html') && str_contains($message, 'automated queries')) {
            return 'Google temporarily blocked this request from our server. Please retry in a moment.';
        }

        return match (true) {
            $status === 429 => 'AI service quota exceeded. Please try again later or check your API plan.',
            in_array($status, [401, 403], true) => 'AI service authentication failed. Please check your service account permissions.',
            $status === 404 => "AI model '{$this->modelName}' not available on Vertex. Check GEMINI_MODEL or GOOGLE_CLOUD_REGION.",
            $status === 400 => 'Invalid request. '.$message,
            $status >= 500 => 'AI service temporarily unavailable. Please try again.',
            default => 'AI service error ('.$status.'). Please try again.',
        };
    }
}
