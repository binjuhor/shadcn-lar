import { ThemeToggle } from "@/components/theme/theme-toggle";
import { Button } from "@/components/ui/button";
import { ArrowRight } from "lucide-react";
import { Link, Head } from "@inertiajs/react";

export const Header = ({ title } : any) => {
  return (
    <>
      <Head title={title ?? 'Shadcn Admin by @Kai from Binjuhor'}/>
      <header className="container mx-auto flex h-fit items-center justify-between py-4">
      <h1 className="flex items-center gap-2 text-xl font-bold">
        <img
          src={"/avatars/shadcn.jpg"}
          width={30}
          height={30}
          className="select-none rounded border shadow-md"
          alt="shadcn/ui"
        />
        <span>Shadcn Admin</span>
      </h1>
      <nav className="flex items-center gap-4">
        <a
          target="_blank"
          href="https://github.com/binjuhor/shadcn-admin"
        >
          <Button
            variant="default"
            className="h-fit rounded-full bg-[#222] font-semibold text-white hover:bg-[#222]/90"
          >
            Github
            <ArrowRight className="ml-2 h-4 w-4" />
          </Button>
        </a>
        <ThemeToggle />
      </nav>
    </header>
    </>
  );
};
