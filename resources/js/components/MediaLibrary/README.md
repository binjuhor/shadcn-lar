# Media Library Components

React components for file uploads adapted from Spatie Laravel Media Library Pro.

## Components

### MediaUploader

A drag-and-drop file uploader with preview support.

#### Usage

```tsx
import { MediaUploader } from '@/components/MediaLibrary';

function MyForm() {
    const [files, setFiles] = useState<File[]>([]);

    const handleChange = (newFiles: File[]) => {
        setFiles(newFiles);
    };

    return (
        <MediaUploader
            name="featured_image"
            multiple={false}
            maxFiles={1}
            acceptedFileTypes={['image/*']}
            maxFileSize={5}
            onChange={handleChange}
        />
    );
}
```

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `name` | `string` | required | Input name attribute |
| `initialMedia` | `MediaFile[]` | `[]` | Existing media files |
| `collection` | `string` | `'default'` | Media library collection name |
| `multiple` | `boolean` | `true` | Allow multiple files |
| `maxFiles` | `number` | `10` | Maximum number of files |
| `acceptedFileTypes` | `string[]` | `['image/*']` | Accepted MIME types |
| `maxFileSize` | `number` | `10` | Maximum file size in MB |
| `onChange` | `(files: File[]) => void` | - | Callback when files change |
| `onRemove` | `(uuid: string) => void` | - | Callback when media removed |

## Example: Blog Post Featured Image

```tsx
<MediaUploader
    name="featured_image"
    multiple={false}
    maxFiles={1}
    acceptedFileTypes={['image/jpeg', 'image/png', 'image/webp']}
    maxFileSize={5}
    onChange={(files) => {
        formData.set('featured_image', files[0]);
    }}
/>
```

## Example: Multiple Images Gallery

```tsx
<MediaUploader
    name="gallery"
    multiple={true}
    maxFiles={20}
    acceptedFileTypes={['image/*']}
    maxFileSize={10}
    onChange={(files) => {
        formData.set('gallery', files);
    }}
/>
```

## Styling

The components use Tailwind CSS classes defined in `resources/css/media-library.css`.
The styles are automatically imported in `resources/css/app.css`.

## TypeScript Types

```typescript
interface MediaFile {
    id?: number;
    uuid: string;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    preview_url?: string;
    order_column?: number;
}
```