<?php

namespace Modules\AiAdvisor\Services;

use Illuminate\Support\Facades\{Cache, Log, Validator};
use Illuminate\Validation\ValidationException;
use Modules\AiAdvisor\Contracts\PreviewsArguments;
use Modules\AiAdvisor\DTOs\{ToolCall, ToolContext, ToolResult};
use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Throwable;

class ToolExecutor
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolConfirmationService $confirmations,
    ) {}

    public function execute(ToolContext $context, ToolCall $call): ToolResult
    {
        $startedAt = microtime(true);
        $outcome = 'error';
        $errorCode = null;

        try {
            $tool = $this->registry->get($call->name);
            $validator = Validator::make($call->arguments, $tool->rules());

            if ($validator->fails()) {
                $errorCode = 'validation_failed';

                return ToolResult::error('validation_failed', 'The tool arguments are invalid.', ['errors' => $validator->errors()->toArray()]);
            }

            if ($tool->riskLevel() === ToolRiskLevel::Write) {
                if (! config('aiadvisor.mutations_enabled', false)) {
                    $errorCode = 'mutations_disabled';

                    return ToolResult::error('mutations_disabled', 'Financial changes are currently disabled.');
                }

                if ($call->confirmed && $call->idempotencyKey) {
                    $idempotencyKey = "aiadvisor:idempotency:{$context->actor->getKey()}:{$call->name}:{$call->idempotencyKey}";
                    $state = Cache::get($idempotencyKey);
                    if (is_array($state) && ($state['status'] ?? null) === 'completed') {
                        $outcome = 'success';

                        return new ToolResult(...$state['result']);
                    }

                    if (($state['status'] ?? null) === 'processing') {
                        $errorCode = 'already_processing';

                        return ToolResult::error('already_processing', 'This financial change is already being processed.');
                    }
                }

                if (! $call->confirmed || ! $this->confirmations->consume($context, $call)) {
                    $errorCode = 'confirmation_required';

                    return ToolResult::error('confirmation_required', 'Confirm this financial change before it is applied.', [
                        'confirmation_token' => $this->confirmations->issue($context, $call),
                        'preview' => $tool instanceof PreviewsArguments ? $tool->previewArguments($context, $call->arguments) : $call->arguments,
                    ]);
                }

                if (! $call->idempotencyKey) {
                    $errorCode = 'idempotency_key_required';

                    return ToolResult::error('idempotency_key_required', 'A confirmed financial change requires an idempotency key.');
                }

                $idempotencyKey = "aiadvisor:idempotency:{$context->actor->getKey()}:{$call->name}:{$call->idempotencyKey}";
                if (! Cache::add($idempotencyKey, ['status' => 'processing'], now()->addDay())) {
                    $errorCode = 'already_processing';

                    return ToolResult::error('already_processing', 'This financial change is already being processed.');
                }
            }

            $result = $tool->execute($context, $validator->validated());
            $outcome = $result->success ? 'success' : 'error';
            $errorCode = $result->errorCode;

            if (isset($idempotencyKey) && $result->success) {
                Cache::put($idempotencyKey, [
                    'status' => 'completed',
                    'result' => [
                        'success' => $result->success,
                        'data' => $result->data,
                        'errorCode' => $result->errorCode,
                        'message' => $result->message,
                        'meta' => $result->meta,
                    ],
                ], now()->addDay());
            }

            return $result;
        } catch (ValidationException $exception) {
            $errorCode = 'validation_failed';
            if (isset($idempotencyKey)) {
                Cache::forget($idempotencyKey);
            }

            return ToolResult::error('validation_failed', 'The financial operation is not valid.', [
                'errors' => $exception->errors(),
            ]);
        } catch (Throwable $exception) {
            $errorCode = 'execution_failed';
            if (isset($idempotencyKey)) {
                Cache::forget($idempotencyKey);
            }

            report($exception);

            return ToolResult::error('execution_failed', 'The financial operation could not be completed.');
        } finally {
            Log::info('AI advisor tool executed', [
                'tool' => $call->name,
                'actor_id' => $context->actor->getKey(),
                'correlation_id' => $context->correlationId,
                'outcome' => $outcome,
                'error_code' => $errorCode,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        }
    }
}
