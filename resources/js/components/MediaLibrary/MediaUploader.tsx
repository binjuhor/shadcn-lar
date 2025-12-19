import React, { useRef, useState } from 'react';
import IconButton from './IconButton';
import Icons from './Icons';
import { Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

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

interface MediaUploaderProps {
    name: string;
    initialMedia?: MediaFile[];
    collection?: string;
    multiple?: boolean;
    maxFiles?: number;
    acceptedFileTypes?: string[];
    maxFileSize?: number; // in MB
    onChange?: (files: File[]) => void;
    onRemove?: (uuid: string) => void;
}

export default function MediaUploader({
    name,
    initialMedia = [],
    collection = 'default',
    multiple = true,
    maxFiles = 10,
    acceptedFileTypes = ['image/*'],
    maxFileSize = 10,
    onChange,
    onRemove,
}: MediaUploaderProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [mediaFiles, setMediaFiles] = useState<MediaFile[]>(initialMedia);
    const [dragActive, setDragActive] = useState(false);
    const [uploadProgress, setUploadProgress] = useState<{ [key: string]: number }>({});
    const [errors, setErrors] = useState<string[]>([]);

    const validateFile = (file: File): string | null => {
        // Check file size
        if (file.size > maxFileSize * 1024 * 1024) {
            return `File ${file.name} exceeds maximum size of ${maxFileSize}MB`;
        }

        // Check file type
        const fileType = file.type;
        const isValidType = acceptedFileTypes.some((type) => {
            if (type.endsWith('/*')) {
                return fileType.startsWith(type.replace('/*', ''));
            }
            return fileType === type;
        });

        if (!isValidType) {
            return `File ${file.name} is not an accepted file type`;
        }

        return null;
    };

    const handleFiles = (files: FileList | null) => {
        if (!files) return;

        const newErrors: string[] = [];
        const validFiles: File[] = [];

        const remainingSlots = maxFiles - (mediaFiles.length + selectedFiles.length);

        Array.from(files).slice(0, remainingSlots).forEach((file) => {
            const error = validateFile(file);
            if (error) {
                newErrors.push(error);
            } else {
                validFiles.push(file);
            }
        });

        if (validFiles.length > 0) {
            const updatedFiles = multiple ? [...selectedFiles, ...validFiles] : [validFiles[0]];
            setSelectedFiles(updatedFiles);
            onChange?.(updatedFiles);
        }

        setErrors(newErrors);
    };

    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFiles(e.dataTransfer.files);
        }
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        e.preventDefault();
        if (e.target.files && e.target.files.length > 0) {
            handleFiles(e.target.files);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    };

    const removeFile = (index: number) => {
        const updatedFiles = selectedFiles.filter((_, i) => i !== index);
        setSelectedFiles(updatedFiles);
        onChange?.(updatedFiles);
    };

    const removeMediaFile = (uuid: string) => {
        setMediaFiles(mediaFiles.filter((file) => file.uuid !== uuid));
        onRemove?.(uuid);
    };

    const canAddMore = mediaFiles.length + selectedFiles.length < maxFiles;

    // Single file upload UI
    if (!multiple) {
        const hasFile = selectedFiles.length > 0 || mediaFiles.length > 0;

        return (
            <div className="grid gap-2">
                <Icons />

                {/* Error messages */}
                {errors.length > 0 && (
                    <div className="space-y-2 mb-2">
                        {errors.map((error, index) => (
                            <p key={index} className="text-sm text-red-500">
                                {error}
                            </p>
                        ))}
                    </div>
                )}

                {hasFile ? (
                    <div className="relative">
                        <img
                            alt={selectedFiles[0]?.name || mediaFiles[0]?.name}
                            className="aspect-video w-full rounded-md object-cover"
                            src={selectedFiles[0] ? URL.createObjectURL(selectedFiles[0]) : mediaFiles[0]?.preview_url}
                        />
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            className="absolute top-2 right-2"
                            onClick={() => {
                                if (selectedFiles.length > 0) {
                                    removeFile(0);
                                } else if (mediaFiles.length > 0) {
                                    removeMediaFile(mediaFiles[0].uuid);
                                }
                            }}
                        >
                            <Icon icon="remove" />
                        </Button>
                    </div>
                ) : (
                    <div
                        className={cn(
                            "flex aspect-video w-full items-center justify-center rounded-md border border-dashed cursor-pointer hover:bg-muted/50 transition-colors",
                            dragActive && "bg-muted/50"
                        )}
                        onDragEnter={handleDrag}
                        onDragLeave={handleDrag}
                        onDragOver={handleDrag}
                        onDrop={handleDrop}
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept={acceptedFileTypes.join(',')}
                            multiple={false}
                            onChange={handleChange}
                            className="hidden"
                            name={`${name}_file`}
                        />
                        <div className="flex flex-col items-center gap-2">
                            <Upload className="h-8 w-8 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                Click to upload thumbnail
                            </p>
                            <p className="text-xs text-muted-foreground">
                                PNG, JPG up to {maxFileSize}MB
                            </p>
                        </div>
                    </div>
                )}

                {/* Hidden inputs for existing media */}
                {mediaFiles.map((file, index) => (
                    <input
                        key={file.uuid}
                        type="hidden"
                        name={`${name}[${index}][uuid]`}
                        value={file.uuid}
                    />
                ))}
            </div>
        );
    }

    // Multiple files upload UI
    return (
        <div className={`media-library ${multiple ? 'media-library-multiple' : 'media-library-single'} ${mediaFiles.length + selectedFiles.length === 0 ? 'media-library-empty' : 'media-library-filled'}`}>
            <Icons />

            {/* Error messages */}
            {errors.length > 0 && (
                <div className="media-library-listerrors">
                    {errors.map((error, index) => (
                        <div key={index} className="media-library-listerror">
                            <div className="media-library-listerror-content">
                                <div className="media-library-listerror-title">{error}</div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Existing media files */}
            {(mediaFiles.length > 0 || selectedFiles.length > 0) && (
                <div className="media-library-items">
                    {mediaFiles.map((file) => (
                        <div key={file.uuid} className="media-library-item media-library-item-row">
                            <div className="media-library-thumb">
                                {file.preview_url ? (
                                    <img
                                        src={file.preview_url}
                                        alt={file.name}
                                        className="media-library-thumb-img"
                                    />
                                ) : (
                                    <span className="media-library-thumb-extension">
                                        <span className="media-library-thumb-extension-truncate">
                                            {file.file_name.split('.').pop()}
                                        </span>
                                    </span>
                                )}
                            </div>
                            <div className="media-library-properties">
                                <span className="media-library-property">{file.name}</span>
                                <span className="media-library-property text-xs text-gray-400">
                                    {(file.size / 1024).toFixed(2)} KB
                                </span>
                            </div>
                            <button
                                type="button"
                                className="media-library-row-remove"
                                onClick={() => removeMediaFile(file.uuid)}
                            >
                                <Icon icon="remove" />
                            </button>
                        </div>
                    ))}

                    {selectedFiles.map((file, index) => (
                        <div key={index} className="media-library-item media-library-item-row">
                            <div className="media-library-thumb">
                                {file.type.startsWith('image/') ? (
                                    <img
                                        src={URL.createObjectURL(file)}
                                        alt={file.name}
                                        className="media-library-thumb-img"
                                    />
                                ) : (
                                    <span className="media-library-thumb-extension">
                                        <span className="media-library-thumb-extension-truncate">
                                            {file.name.split('.').pop()}
                                        </span>
                                    </span>
                                )}
                            </div>
                            <div className="media-library-properties">
                                <span className="media-library-property">{file.name}</span>
                                <span className="media-library-property text-xs text-gray-400">
                                    {(file.size / 1024).toFixed(2)} KB
                                </span>
                            </div>
                            <button
                                type="button"
                                className="media-library-row-remove"
                                onClick={() => removeFile(index)}
                            >
                                <Icon icon="remove" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Upload area */}
            {canAddMore && (
                <div className="media-library-uploader media-library-add">
                    <div
                        className={`media-library-dropzone media-library-dropzone-add ${dragActive ? 'media-library-dropzone-drag' : ''}`}
                        onDragEnter={handleDrag}
                        onDragLeave={handleDrag}
                        onDragOver={handleDrag}
                        onDrop={handleDrop}
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept={acceptedFileTypes.join(',')}
                            multiple={multiple}
                            onChange={handleChange}
                            className="media-library-hidden"
                            name={`${name}_files`}
                        />

                        <div className="media-library-placeholder">
                            <IconButton level="info" icon="add" />
                        </div>

                        <div className="media-library-help">
                            <span>
                                Drop files here or click to upload{' '}
                                {multiple && `(${mediaFiles.length + selectedFiles.length}/${maxFiles})`}
                            </span>
                            <br />
                            <span className="text-xs text-gray-500">
                                Max {maxFileSize}MB per file
                            </span>
                        </div>
                    </div>
                </div>
            )}

            {/* Hidden inputs for existing media */}
            {mediaFiles.map((file, index) => (
                <input
                    key={file.uuid}
                    type="hidden"
                    name={`${name}[${index}][uuid]`}
                    value={file.uuid}
                />
            ))}
        </div>
    );
}

// Re-export Icon for convenience
import Icon from './Icon';
