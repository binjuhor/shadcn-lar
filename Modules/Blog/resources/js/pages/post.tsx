import {AuthenticatedLayout} from "@/layouts"
import {
  ChevronLeft,
  Calendar,
  Clock,
  User,
  Tag,
  Eye,
  Edit,
  Share2,
} from "lucide-react"
import { useTranslation } from "react-i18next"

import {Badge} from "@/components/ui/badge"
import {Button} from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Main } from "@/components/layout"
import type { BlogPost } from "@/types/blog"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"

interface BlogPostPageProps extends PageProps {
  post: BlogPost
}

export default function BlogPost({ post }: BlogPostPageProps) {
  const { t } = useTranslation()

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
    })
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "published":
        return <Badge variant="outline" className="text-green-600 border-green-600">{t('common.statuses.published')}</Badge>
      case "draft":
        return <Badge variant="secondary">{t('common.statuses.draft')}</Badge>
      case "archived":
        return <Badge variant="outline">{t('common.statuses.archived')}</Badge>
      default:
        return <Badge>{status}</Badge>
    }
  }

  return (
    <>
      <AuthenticatedLayout title={post.title}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="grid flex-1 auto-rows-max gap-4">
              <div className="flex items-center gap-4">
                <Button
                  variant="outline"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => window.history.back()}
                >
                  <ChevronLeft className="h-4 w-4" />
                  <span className="sr-only">{t('common.actions.back')}</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  {t('page.blog.posts.title')}
                </h1>
                {getStatusBadge(post.status)}
                {post.is_featured && (
                  <Badge variant="secondary" className="ml-2">
                    {t('page.blog.posts.featured')}
                  </Badge>
                )}
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button variant="outline" size="sm">
                    <Share2 className="h-4 w-4 mr-2" />
                    {t('page.blog.posts.actions.share')}
                  </Button>
                  <Button variant="outline" size="sm">
                    <Eye className="h-4 w-4 mr-2" />
                    {t('page.blog.posts.actions.preview')}
                  </Button>
                  <Button
                    size="sm"
                    onClick={() => router.get(route('dashboard.posts.edit', post.slug))}
                  >
                    <Edit className="h-4 w-4 mr-2" />
                    {t('page.blog.posts.actions.edit_post')}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_300px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4">
                  <Card>
                    <CardHeader>
                      <div className="space-y-4">
                        {post.featured_image && (
                          <img
                            src={post.featured_image}
                            alt={post.title}
                            className="w-full h-48 object-cover rounded-lg"
                          />
                        )}
                        <div>
                          <CardTitle className="text-2xl leading-tight">
                            {post.title}
                          </CardTitle>
                          <CardDescription className="mt-2 text-base">
                            {post.excerpt}
                          </CardDescription>
                        </div>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <div
                        className="prose prose-sm max-w-none dark:prose-invert"
                        dangerouslySetInnerHTML={{ __html: post.content }}
                      />
                    </CardContent>
                  </Card>
                </div>

                <div className="grid auto-rows-max items-start gap-4">
                  <Card>
                    <CardHeader>
                      <CardTitle className="text-lg">{t('page.blog.posts.post_information')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <User className="h-4 w-4" />
                        <div className="flex items-center gap-2">
                          <img
                            src="/placeholder.svg"
                            alt={post.user.name}
                            className="w-5 h-5 rounded-full"
                          />
                          <span>{post.user.name}</span>
                        </div>
                      </div>

                      {post.published_at && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                          <Calendar className="h-4 w-4" />
                          <span>{t('page.blog.posts.published_date', { date: formatDate(post.published_at) })}</span>
                        </div>
                      )}

                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Clock className="h-4 w-4" />
                        <span>{t('page.blog.posts.updated_date', { date: formatDate(post.updated_at) })}</span>
                      </div>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle className="text-lg">{t('common.fields.category')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      {post.category && (
                        <Badge variant="outline" className="text-sm">
                          {post.category.name}
                        </Badge>
                      )}
                    </CardContent>
                  </Card>

                  {post.tags && post.tags.length > 0 && (
                    <Card>
                      <CardHeader>
                        <CardTitle className="text-lg flex items-center gap-2">
                          <Tag className="h-4 w-4" />
                          {t('page.blog.posts.tags')}
                        </CardTitle>
                      </CardHeader>
                      <CardContent>
                        <div className="flex flex-wrap gap-2">
                          {post.tags.map((tag) => (
                            <Badge key={tag.id} variant="secondary" className="text-xs">
                              {tag.name}
                            </Badge>
                          ))}
                        </div>
                      </CardContent>
                    </Card>
                  )}

                  <Card>
                    <CardHeader>
                      <CardTitle className="text-lg">{t('page.blog.posts.statistics')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                      <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">{t('page.blog.posts.views')}</span>
                        <span className="font-medium">{post.views_count || 0}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">{t('page.blog.posts.reading_time')}</span>
                        <span className="font-medium">{t('page.blog.posts.reading_time_value', { minutes: post.reading_time || 0 })}</span>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>

              <div className="flex items-center justify-center gap-2 md:hidden">
                <Button variant="outline" size="sm">
                  <Share2 className="h-4 w-4 mr-2" />
                  {t('page.blog.posts.actions.share')}
                </Button>
                <Button variant="outline" size="sm">
                  <Eye className="h-4 w-4 mr-2" />
                  {t('page.blog.posts.actions.preview')}
                </Button>
                <Button
                  size="sm"
                  onClick={() => router.get(route('dashboard.posts.edit', post.slug))}
                >
                  <Edit className="h-4 w-4 mr-2" />
                  {t('page.blog.posts.actions.edit_post')}
                </Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
