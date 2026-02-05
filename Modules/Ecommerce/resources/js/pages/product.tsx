import {AuthenticatedLayout} from "@/layouts"
import { ChevronLeft, Calendar, Clock, User, Tag, Eye, Edit, Package, DollarSign } from "lucide-react"
import { useTranslation } from 'react-i18next'
import {Badge} from "@/components/ui/badge"
import {Button} from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Main } from "@/components/layout"
import type { Product } from "@/types/ecommerce"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"

interface ProductPageProps extends PageProps {
  product: Product
}

export default function ProductShow({ product }: ProductPageProps) {
  const { t } = useTranslation()

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
    })
  }

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(price)
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "active":
        return <Badge variant="outline" className="text-green-600 border-green-600">{t('common.statuses.active')}</Badge>
      case "draft":
        return <Badge variant="secondary">{t('common.statuses.draft')}</Badge>
      case "archived":
        return <Badge variant="outline">{t('common.statuses.archived')}</Badge>
      default:
        return <Badge>{status}</Badge>
    }
  }

  const getStockBadge = () => {
    if (product.is_out_of_stock) {
      return <Badge variant="destructive">{t('page.ecommerce.products.status.out_of_stock')}</Badge>
    }
    if (product.is_low_stock) {
      return <Badge variant="outline" className="text-orange-600 border-orange-600">{t('page.ecommerce.products.status.low_stock')}</Badge>
    }
    return <Badge variant="outline" className="text-green-600 border-green-600">{t('page.ecommerce.products.status.in_stock')}</Badge>
  }

  return (
    <>
      <AuthenticatedLayout title={product.name}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="grid flex-1 auto-rows-max gap-4">
              <div className="flex items-center gap-4">
                <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                  <ChevronLeft className="h-4 w-4" />
                  <span className="sr-only">{t('common.actions.back')}</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  {t('page.ecommerce.product.title')}
                </h1>
                {getStatusBadge(product.status)}
                {product.is_featured && (
                  <Badge variant="secondary" className="ml-2">
                    {t('page.ecommerce.products.status.featured')}
                  </Badge>
                )}
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button variant="outline" size="sm">
                    <Eye className="h-4 w-4 mr-2" />
                    {t('common.actions.preview')}
                  </Button>
                  <Button size="sm" onClick={() => router.get(route('dashboard.ecommerce.products.edit', product.slug))}>
                    <Edit className="h-4 w-4 mr-2" />
                    {t('page.ecommerce.product.actions.edit_product')}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_300px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4">
                  <Card>
                    <CardHeader>
                      <div className="space-y-4">
                        {product.featured_image_url && (
                          <img
                            src={product.featured_image_url}
                            alt={product.name}
                            className="w-full h-48 object-cover rounded-lg"
                          />
                        )}
                        <div>
                          <CardTitle className="text-2xl leading-tight">
                            {product.name}
                          </CardTitle>
                          <CardDescription className="mt-2 text-base">
                            {product.description}
                          </CardDescription>
                        </div>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <div
                        className="prose prose-sm max-w-none dark:prose-invert"
                        dangerouslySetInnerHTML={{ __html: product.content || '' }}
                      />
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle className="text-lg">{t('page.ecommerce.product.section.pricing_inventory')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="grid grid-cols-2 gap-4">
                        <div>
                          <div className="text-sm text-muted-foreground">{t('common.fields.price')}</div>
                          <div className="text-2xl font-bold">{formatPrice(product.price)}</div>
                        </div>
                        {product.is_on_sale && product.sale_price && (
                          <div>
                            <div className="text-sm text-muted-foreground">{t('page.ecommerce.product.form.sale_price')}</div>
                            <div className="text-2xl font-bold text-green-600">{formatPrice(product.sale_price)}</div>
                            <Badge variant="destructive" className="mt-1">-{product.discount_percentage}%</Badge>
                          </div>
                        )}
                      </div>
                      <div className="grid grid-cols-2 gap-4">
                        <div>
                          <div className="text-sm text-muted-foreground">{t('common.fields.sku')}</div>
                          <div className="font-mono">{product.sku || 'N/A'}</div>
                        </div>
                        <div>
                          <div className="text-sm text-muted-foreground">{t('page.ecommerce.product.section.stock_status')}</div>
                          <div className="mt-1">{getStockBadge()}</div>
                        </div>
                      </div>
                      <div className="grid grid-cols-2 gap-4">
                        <div>
                          <div className="text-sm text-muted-foreground">{t('page.ecommerce.product.form.stock_quantity')}</div>
                          <div className="font-medium">{product.stock_quantity} {t('page.ecommerce.product.units')}</div>
                        </div>
                        {product.low_stock_threshold && (
                          <div>
                            <div className="text-sm text-muted-foreground">{t('page.ecommerce.product.form.low_stock_threshold')}</div>
                            <div className="font-medium">{product.low_stock_threshold} {t('page.ecommerce.product.units')}</div>
                          </div>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                </div>

                <div className="grid auto-rows-max items-start gap-4">
                  <Card>
                    <CardHeader>
                      <CardTitle className="text-lg">{t('page.ecommerce.product.section.product_information')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <User className="h-4 w-4" />
                        <div className="flex items-center gap-2">
                          <img
                            src="/placeholder.svg"
                            alt={product.user.name}
                            className="w-5 h-5 rounded-full"
                          />
                          <span>{product.user.name}</span>
                        </div>
                      </div>

                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Calendar className="h-4 w-4" />
                        <span>{t('page.ecommerce.product.created', { date: formatDate(product.created_at) })}</span>
                      </div>

                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Clock className="h-4 w-4" />
                        <span>{t('page.ecommerce.product.updated', { date: formatDate(product.updated_at) })}</span>
                      </div>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle className="text-lg">{t('page.ecommerce.product.section.category')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      {product.category ? (
                        <Badge variant="outline" className="text-sm">
                          {product.category.name}
                        </Badge>
                      ) : (
                        <span className="text-sm text-muted-foreground">{t('page.ecommerce.product.no_category')}</span>
                      )}
                    </CardContent>
                  </Card>

                  {product.tags && product.tags.length > 0 && (
                    <Card>
                      <CardHeader>
                        <CardTitle className="text-lg flex items-center gap-2">
                          <Tag className="h-4 w-4" />
                          {t('page.ecommerce.product.section.tags')}
                        </CardTitle>
                      </CardHeader>
                      <CardContent>
                        <div className="flex flex-wrap gap-2">
                          {product.tags.map((tag) => (
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
                      <CardTitle className="text-lg">{t('page.ecommerce.product.section.statistics')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                      <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">{t('page.ecommerce.product.stats.views')}</span>
                        <span className="font-medium">{product.views_count || 0}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">{t('page.ecommerce.product.stats.sales')}</span>
                        <span className="font-medium">{product.sales_count || 0}</span>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>

              <div className="flex items-center justify-center gap-2 md:hidden">
                <Button variant="outline" size="sm">
                  <Eye className="h-4 w-4 mr-2" />
                  {t('common.actions.preview')}
                </Button>
                <Button size="sm" onClick={() => router.get(route('dashboard.ecommerce.products.edit', product.slug))}>
                  <Edit className="h-4 w-4 mr-2" />
                  {t('page.ecommerce.product.actions.edit_product')}
                </Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
