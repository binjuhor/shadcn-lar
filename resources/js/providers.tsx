import * as React from "react"
import {QueryClientProvider} from "@tanstack/react-query"
import {ReactQueryDevtools} from "@tanstack/react-query-devtools"
import {HelmetProvider} from "react-helmet-async"
import {Toaster} from "@/components/ui/toaster"
import {Toaster as SonnerToaster} from "sonner"
import {TooltipProvider} from "@/components/ui/tooltip"
import {NuqsAdapter} from "@/lib/nuqs"
import {queryClient} from "@/lib/react-query"
import {ThemeProvider} from "@/context/theme-context"
import {SearchProvider} from "@/context/search-context"
import {CommandMenu} from "@/components/command-menu"

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
              <SonnerToaster position="bottom-right" />
              <CommandMenu />
            </ThemeProvider>
          </SearchProvider>

          {/* Devtools */}
          <ReactQueryDevtools buttonPosition={"bottom-right"}/>
        </QueryClientProvider>
      </NuqsAdapter>
    </HelmetProvider>
  )
}
