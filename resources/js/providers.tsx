import * as React from "react"
import {QueryClientProvider} from "@tanstack/react-query"
import {ReactQueryDevtools} from "@tanstack/react-query-devtools"
import {HelmetProvider} from "react-helmet-async"
import {Toaster} from "@/components/ui/toaster"
import {TooltipProvider} from "@/components/ui/tooltip"
import {NuqsAdapter} from "@/lib/nuqs"
import {queryClient} from "@/lib/react-query"
import {ThemeProvider} from "@/context/theme-context"
import {SearchProvider} from "@/context/search-context"

export function Providers({children}: any) {
  return (
    <HelmetProvider>
      <NuqsAdapter>
        <QueryClientProvider client={queryClient}>
          <SearchProvider>
            <ThemeProvider
              defaultTheme="light"
              storageKey="app-ui-theme"
            >
              <TooltipProvider>{children}</TooltipProvider>

              <Toaster/>
            </ThemeProvider>
          </SearchProvider>

          {/* Devtools */}
          <ReactQueryDevtools buttonPosition={"bottom-right"}/>
        </QueryClientProvider>
      </NuqsAdapter>
    </HelmetProvider>
  )
}
