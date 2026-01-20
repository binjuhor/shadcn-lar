# Image Generation Reference

Comprehensive guide for image creation, editing, and composition using Imagen 4 and Gemini models.

## Core Capabilities

- **Text-to-Image**: Generate images from text prompts
- **Image Editing**: Modify existing images with text instructions
- **Multi-Image Composition**: Combine up to 3 images
- **Iterative Refinement**: Refine images conversationally
- **Aspect Ratios**: Multiple formats (1:1, 16:9, 9:16, 4:3, 3:4)
- **Quality Variants**: Standard/Ultra/Fast for different needs
- **Text in Images**: Limited text rendering (varies by model)

## Models

### Imagen 4 (Recommended)

**imagen-4.0-generate-001** - Standard quality, balanced performance
- Best for: General use, prototyping, iterative workflows
- Quality: High
- Speed: Medium (~5-10s per image)
- Cost: ~$0.02/image (estimated)
- Output: 1-4 images per request
- Resolution: 1K or 2K
- Updated: June 2025

**imagen-4.0-ultra-generate-001** - Maximum quality
- Best for: Final production, marketing assets, detailed artwork
- Quality: Ultra (highest available)
- Speed: Slow (~15-25s per image)
- Cost: ~$0.04/image (estimated)
- Output: 1-4 images per request
- Resolution: 2K preferred
- Updated: June 2025

**imagen-4.0-fast-generate-001** - Fastest generation
- Best for: Rapid iteration, bulk generation, real-time use
- Quality: Good
- Speed: Fast (~2-5s per image)
- Cost: ~$0.01/image (estimated)
- Output: 1-4 images per request
- Resolution: 1K
- Updated: June 2025

### Gemini 3 Pro Image (Alternative)

**gemini-3-pro-image-preview** - Conversational image generation
- Best for: Iterative refinement with natural language editing
- Quality: High
- Context: 65k input / 32k output tokens
- Cost: $2/1M text input, $0.134/image output (resolution-dependent)
- Unique: Native 4K text rendering, grounded generation
- Updated: January 2025

### Legacy Models

**gemini-2.5-flash-image** - Legacy image generation
- Status: Deprecated (use Imagen 4 instead)
- Still functional for backward compatibility
- Input: 65,536 tokens
- Output: 32,768 tokens
- Cost: $1/1M input

## Model Comparison

| Model | Quality | Speed | Cost | Best For |
|-------|---------|-------|------|----------|
| imagen-4.0-ultra | ⭐⭐⭐⭐⭐ | 🐢 Slow | 💰💰 High | Production assets |
| imagen-4.0-standard | ⭐⭐⭐⭐ | ⚡ Medium | 💰 Medium | General use |
| imagen-4.0-fast | ⭐⭐⭐ | 🚀 Fast | 💵 Low | Rapid iteration |
| gemini-3-pro-image | ⭐⭐⭐⭐ | ⚡ Medium | 💰 Medium | Text rendering |
| gemini-2.5-flash-image | ⭐⭐⭐ | ⚡ Medium | 💵 Low | Legacy (deprecated) |

**Selection Guide**:
- **Marketing/Production**: Use `imagen-4.0-ultra` for final deliverables
- **General Development**: Use `imagen-4.0-generate-001` for balanced workflow
- **Prototyping/Iteration**: Use `imagen-4.0-fast` for quick feedback
- **Text-Heavy Images**: Use `gemini-3-pro-image` for 4K text rendering

## Quick Start

### Basic Generation (Imagen 4)

```python
from google import genai
from google.genai import types
import os

client = genai.Client(api_key=os.getenv('GEMINI_API_KEY'))

# Standard quality (recommended)
response = client.models.generate_images(
    model='imagen-4.0-generate-001',
    prompt='A serene mountain landscape at sunset with snow-capped peaks',
    config=types.GenerateImagesConfig(
        numberOfImages=1,
        aspectRatio='16:9',
        imageSize='1K'
    )
)

# Save images
for i, generated_image in enumerate(response.generated_images):
    with open(f'output-{i}.png', 'wb') as f:
        f.write(generated_image.image.image_bytes)
```

### Quality Variants

```python
# Ultra quality (production)
response = client.models.generate_images(
    model='imagen-4.0-ultra-generate-001',
    prompt='Professional product photography of smartphone',
    config=types.GenerateImagesConfig(
        numberOfImages=1,
        imageSize='2K'  # Use 2K for ultra (Standard/Ultra only)
    )
)

# Fast generation (iteration)
# Note: Fast model doesn't support imageSize parameter
response = client.models.generate_images(
    model='imagen-4.0-fast-generate-001',
    prompt='Quick concept sketch of robot character',
    config=types.GenerateImagesConfig(
        numberOfImages=4,  # Generate multiple variants (default: 4)
        aspectRatio='1:1'
    )
)
```

### Legacy Flash Image (Backward Compatibility)

```python
# Still works but deprecated
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='A futuristic cityscape',
    config=types.GenerateContentConfig(
        response_modalities=['Image'],
        image_config=types.ImageConfig(
            aspect_ratio='16:9'
        )
    )
)

# Save from content parts
for i, part in enumerate(response.candidates[0].content.parts):
    if part.inline_data:
        with open(f'output-{i}.png', 'wb') as f:
            f.write(part.inline_data.data)
```

## API Differences

### Imagen 4 vs Flash Image

**Imagen 4** uses `generate_images()`:
```python
response = client.models.generate_images(
    model='imagen-4.0-generate-001',
    prompt='...',
    config=types.GenerateImagesConfig(
        numberOfImages=1,
        aspectRatio='16:9',
        imageSize='1K'  # Standard/Ultra only
    )
)
# Access: response.generated_images[0].image.image_bytes
```

**Flash Image** uses `generate_content()`:
```python
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='...',
    config=types.GenerateContentConfig(
        response_modalities=['Image'],
        image_config=types.ImageConfig(...)
    )
)
# Access: response.candidates[0].content.parts[0].inline_data.data
```

**Key Differences**:
1. Different method: `generate_images()` vs `generate_content()`
2. Different config: `GenerateImagesConfig` (camelCase params) vs `GenerateContentConfig`
3. Parameter names: `prompt` vs `contents`, `numberOfImages` (camelCase) vs `number_of_images` (snake_case)
4. Response structure: `response.generated_images[i].image.image_bytes` vs `response.candidates[0].content.parts`
5. Fast model limitation: No `imageSize` parameter support

## Aspect Ratios

| Ratio | Resolution | Use Case | Token Cost |
|-------|-----------|----------|------------|
| 1:1 | 1024×1024 | Social media, avatars | 1290 |
| 16:9 | 1344×768 | Landscapes, banners | 1290 |
| 9:16 | 768×1344 | Mobile, portraits | 1290 |
| 4:3 | 1152×896 | Traditional media | 1290 |
| 3:4 | 896×1152 | Vertical posters | 1290 |

All ratios cost the same: 1,290 tokens per image.

## Response Modalities

### Image Only

```python
config = types.GenerateContentConfig(
    response_modalities=['image'],
    aspect_ratio='1:1'
)
```

### Text Only (No Image)

```python
config = types.GenerateContentConfig(
    response_modalities=['text']
)
# Returns text description instead of generating image
```

### Both Image and Text

```python
config = types.GenerateContentConfig(
    response_modalities=['image', 'text'],
    aspect_ratio='16:9'
)
# Returns both generated image and description
```

## Image Editing

### Modify Existing Image

```python
import PIL.Image

# Load original
img = PIL.Image.open('original.png')

# Edit with instructions
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents=[
        'Add a red balloon floating in the sky',
        img
    ],
    config=types.GenerateContentConfig(
        response_modalities=['image'],
        aspect_ratio='16:9'
    )
)
```

### Style Transfer

```python
img = PIL.Image.open('photo.jpg')

response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents=[
        'Transform this into an oil painting style',
        img
    ]
)
```

### Object Addition/Removal

```python
# Add object
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents=[
        'Add a vintage car parked on the street',
        img
    ]
)

# Remove object
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents=[
        'Remove the person on the left side',
        img
    ]
)
```

## Multi-Image Composition

### Combine Multiple Images

```python
img1 = PIL.Image.open('background.png')
img2 = PIL.Image.open('foreground.png')
img3 = PIL.Image.open('overlay.png')

response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents=[
        'Combine these images into a cohesive scene',
        img1,
        img2,
        img3
    ],
    config=types.GenerateContentConfig(
        response_modalities=['image'],
        aspect_ratio='16:9'
    )
)
```

**Note**: Recommended maximum 3 input images for best results.

## Prompt Engineering

### Effective Prompt Structure

**Three key elements**:
1. **Subject**: What to generate
2. **Context**: Environmental setting
3. **Style**: Artistic treatment

**Example**: "A robot [subject] in a futuristic city [context], cyberpunk style with neon lighting [style]"

### Quality Modifiers

**Technical terms**:
- "4K", "8K", "high resolution"
- "HDR", "high dynamic range"
- "professional photography"
- "studio lighting"
- "ultra detailed"

**Camera settings**:
- "35mm lens", "50mm lens"
- "shallow depth of field"
- "wide angle shot"
- "macro photography"
- "golden hour lighting"

### Style Keywords

**Art styles**:
- "oil painting", "watercolor", "sketch"
- "digital art", "concept art"
- "photorealistic", "hyperrealistic"
- "minimalist", "abstract"
- "cyberpunk", "steampunk", "fantasy"

**Mood and atmosphere**:
- "dramatic lighting", "soft lighting"
- "moody", "bright and cheerful"
- "mysterious", "whimsical"
- "dark and gritty", "pastel colors"

### Subject Description

**Be specific**:
- ❌ "A cat"
- ✅ "A fluffy orange tabby cat with green eyes"

**Add context**:
- ❌ "A building"
- ✅ "A modern glass skyscraper reflecting sunset clouds"

**Include details**:
- ❌ "A person"
- ✅ "A young woman in a red dress holding an umbrella"

### Composition and Framing

**Camera angles**:
- "bird's eye view", "aerial shot"
- "low angle", "high angle"
- "close-up", "wide shot"
- "centered composition"
- "rule of thirds"

**Perspective**:
- "first person view"
- "third person perspective"
- "isometric view"
- "forced perspective"

### Text in Images

**Limitations**:
- Maximum 25 characters total
- Up to 3 distinct text phrases
- Works best with simple text

**Best practices**:
```python
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='A vintage poster with bold text "EXPLORE" at the top, mountain landscape, retro 1950s style'
)
```

**Font control**:
- "bold sans-serif title"
- "handwritten script"
- "vintage letterpress"
- "modern minimalist font"

## Advanced Techniques

### Iterative Refinement

```python
# Initial generation
response1 = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='A futuristic city skyline'
)

# Save first version
with open('v1.png', 'wb') as f:
    f.write(response1.candidates[0].content.parts[0].inline_data.data)

# Refine
img = PIL.Image.open('v1.png')
response2 = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents=[
        'Add flying vehicles and neon signs',
        img
    ]
)
```

### Negative Prompts (Indirect)

```python
# Instead of "no blur", be specific about what you want
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='A crystal clear, sharp photograph of a diamond ring with perfect focus and high detail'
)
```

### Consistent Style Across Images

```python
base_prompt = "Digital art, vibrant colors, cel-shaded style, clean lines"

prompts = [
    f"{base_prompt}, a warrior character",
    f"{base_prompt}, a mage character",
    f"{base_prompt}, a rogue character"
]

for i, prompt in enumerate(prompts):
    response = client.models.generate_content(
        model='gemini-2.5-flash-image',
        contents=prompt
    )
    # Save each character
```

## Safety Settings

### Configure Safety Filters

```python
config = types.GenerateContentConfig(
    response_modalities=['image'],
    safety_settings=[
        types.SafetySetting(
            category=types.HarmCategory.HARM_CATEGORY_HATE_SPEECH,
            threshold=types.HarmBlockThreshold.BLOCK_MEDIUM_AND_ABOVE
        ),
        types.SafetySetting(
            category=types.HarmCategory.HARM_CATEGORY_SEXUALLY_EXPLICIT,
            threshold=types.HarmBlockThreshold.BLOCK_MEDIUM_AND_ABOVE
        )
    ]
)
```

### Available Categories

- `HARM_CATEGORY_HATE_SPEECH`
- `HARM_CATEGORY_DANGEROUS_CONTENT`
- `HARM_CATEGORY_HARASSMENT`
- `HARM_CATEGORY_SEXUALLY_EXPLICIT`

### Thresholds

- `BLOCK_NONE`: No blocking
- `BLOCK_LOW_AND_ABOVE`: Block low probability and above
- `BLOCK_MEDIUM_AND_ABOVE`: Block medium and above (default)
- `BLOCK_ONLY_HIGH`: Block only high probability

## Common Use Cases

### 1. Marketing Assets

```python
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='''Professional product photography:
    - Sleek smartphone on minimalist white surface
    - Dramatic side lighting creating subtle shadows
    - Shallow depth of field, crisp focus
    - Clean, modern aesthetic
    - 4K quality
    ''',
    config=types.GenerateContentConfig(
        response_modalities=['image'],
        aspect_ratio='4:3'
    )
)
```

### 2. Concept Art

```python
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='''Fantasy concept art:
    - Ancient floating islands connected by chains
    - Waterfalls cascading into clouds below
    - Magical crystals glowing on the islands
    - Epic scale, dramatic lighting
    - Detailed digital painting style
    ''',
    config=types.GenerateContentConfig(
        response_modalities=['image'],
        aspect_ratio='16:9'
    )
)
```

### 3. Social Media Graphics

```python
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='''Instagram post design:
    - Pastel gradient background (pink to blue)
    - Motivational quote layout
    - Modern minimalist style
    - Clean typography
    - Mobile-friendly composition
    ''',
    config=types.GenerateContentConfig(
        response_modalities=['image'],
        aspect_ratio='1:1'
    )
)
```

### 4. Illustration

```python
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='''Children's book illustration:
    - Friendly cartoon dragon reading a book
    - Bright, cheerful colors
    - Soft, rounded shapes
    - Whimsical forest background
    - Warm, inviting atmosphere
    ''',
    config=types.GenerateContentConfig(
        response_modalities=['image'],
        aspect_ratio='4:3'
    )
)
```

### 5. UI/UX Mockups

```python
response = client.models.generate_content(
    model='gemini-2.5-flash-image',
    contents='''Modern mobile app interface:
    - Clean dashboard design
    - Card-based layout
    - Soft shadows and gradients
    - Contemporary color scheme (blue and white)
    - Professional fintech aesthetic
    ''',
    config=types.GenerateContentConfig(
        response_modalities=['image'],
        aspect_ratio='9:16'
    )
)
```

## Best Practices

### Prompt Quality

1. **Be specific**: More detail = better results
2. **Order matters**: Most important elements first
3. **Use examples**: Reference known styles or artists
4. **Avoid contradictions**: Don't ask for opposing styles
5. **Test and iterate**: Refine prompts based on results

### File Management

```python
# Save with descriptive names
timestamp = int(time.time())
filename = f'generated_{timestamp}_{aspect_ratio}.png'

with open(filename, 'wb') as f:
    f.write(image_data)
```

### Cost Optimization

**Token costs**:
- 1 image: 1,290 tokens = $0.00129 (Flash Image at $1/1M)
- 10 images: 12,900 tokens = $0.0129
- 100 images: 129,000 tokens = $0.129

**Strategies**:
- Generate fewer iterations
- Use text modality first to validate concept
- Batch similar requests
- Cache prompts for consistent style

## Error Handling

### Safety Filter Blocking

```python
try:
    response = client.models.generate_content(
        model='gemini-2.5-flash-image',
        contents=prompt
    )
except Exception as e:
    # Check block reason
    if hasattr(e, 'prompt_feedback'):
        print(f"Blocked: {e.prompt_feedback.block_reason}")
        # Modify prompt and retry
```

### Token Limit Exceeded

```python
# Keep prompts concise
if len(prompt) > 1000:
    # Truncate or simplify
    prompt = prompt[:1000]
```

## Limitations

### Imagen 4 Constraints
- **Language**: English prompts only
- **Prompt length**: Maximum 480 tokens
- **Output**: 1-4 images per request
- **Watermark**: All images include SynthID watermark
- **Fast model**: No `imageSize` parameter support (fixed resolution)
- **Text rendering**: Limited to ~25 characters for optimal results
- **Regional restrictions**: Child images restricted in EEA, CH, UK
- **Cannot replicate**: Specific people or copyrighted characters

### General Limitations
- Maximum 3 input images for composition
- No video or animation generation
- No real-time generation

## Troubleshooting

### aspect_ratio Parameter Error

**Error**: `Extra inputs are not permitted [type=extra_forbidden, input_value='1:1', input_type=str]`

**Cause**: The `aspect_ratio` parameter must be nested inside an `image_config` object, not passed directly to `GenerateContentConfig`.

**Incorrect Usage**:
```python
# ❌ This will fail
config = types.GenerateContentConfig(
    response_modalities=['image'],
    aspect_ratio='16:9'  # Wrong - not a direct parameter
)
```

**Correct Usage**:
```python
# ✅ Correct implementation
config = types.GenerateContentConfig(
    response_modalities=['Image'],  # Note: Capital 'I'
    image_config=types.ImageConfig(
        aspect_ratio='16:9'
    )
)
```

### Response Modality Case Sensitivity

The `response_modalities` parameter expects capital case values:
- ✅ Correct: `['Image']`, `['Text']`, `['Image', 'Text']`
- ❌ Wrong: `['image']`, `['text']`

---

## Related References

**Current**: Image Generation

**Related Capabilities**:
- [Image Understanding](./vision-understanding.md) - Analyzing and editing reference images
- [Video Generation](./video-generation.md) - Creating animated video content
- [Audio Processing](./audio-processing.md) - Text-to-speech for multimedia

**Back to**: [AI Multimodal Skill](../SKILL.md)
