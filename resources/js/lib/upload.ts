import axiosRequest from "axios"
import { axios } from "@/lib/axios"
import { assetUrl } from "@/lib/urls"

export type BucketId = "uploads/images" | "uploads/avatars" | "uploads/attachments"

export interface PresignedResponse {
  path: string
  url: string
  headers: Record<string, string>
}

export interface UploaderOptions {
  unique?: boolean
  bucketId?: BucketId
  onUploadProgress?: (event: { loaded: number; total?: number }) => void
}

function createImageUploader({
  unique = true,
  bucketId = "uploads/images",
  onUploadProgress,
}: UploaderOptions = {}) {
  return async (file: File): Promise<string> => {
    const response = await axios.request<PresignedResponse>({
      method: "POST",
      url: "/dashboard/generate-presigned-url",
      data: {
        unique,
        bucketId,
        filename: file.name,
        mimeType: file.type,
        contentLength: file.size,
      },
    })

    const { path, url, headers } = response.data

    await axiosRequest.request({
      method: "PUT",
      url,
      data: file,
      onUploadProgress,
      headers: {
        "Content-Type": file.type,
        "Content-Disposition": `inline; filename="${file.name}"`,
        ...headers,
      },
    })

    return assetUrl(path)
  }
}

export { createImageUploader }
