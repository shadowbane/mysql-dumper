// Updated pagination.tsx
import {Button} from "@/components/ui/button";
import {ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight} from "lucide-react";
import {router} from "@inertiajs/react";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

interface PaginationProps {
    currentPage: number;
    lastPage: number;
    total: number;
    from: number;
    to: number;
    perPage?: number;
    pageName?: string | null;
}

export function Pagination({
                               currentPage,
                               lastPage,
                               total,
                               from,
                               to,
                               perPage = 10,
                               pageName = 'page',
                           }: PaginationProps) {
    const getCurrentPerPage = () => {
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            const urlPerPage = url.searchParams.get('perPage');
            return urlPerPage ? urlPerPage : perPage.toString();
        }
        return perPage.toString();
    };

    const navigate = (page: number) => {
        // Get current URL and preserve all existing query parameters
        const url = new URL(window.location.href);

        // Update only the page parameter
        url.searchParams.set('page', page.toString());

        // Use the full URL with all parameters
        router.get(url.pathname + url.search, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const changePerPage = (value: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('perPage', value);

        // Reset to page 1 when changing items per page
        url.searchParams.set(pageName, '1');

        router.get(url.pathname + url.search, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <div
            className="flex flex-col md:flex-row md:items-center justify-between px-2 py-4 gap-4 border-t border-border">
            <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                <div className="flex items-center space-x-2">
                    <span className="text-sm text-muted-foreground">Items per page:</span>
                    <Select
                        value={getCurrentPerPage()}
                        onValueChange={changePerPage}
                    >
                        <SelectTrigger className="w-[80px] h-9 bg-background">
                            <SelectValue placeholder={perPage.toString()}/>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="10">10</SelectItem>
                            <SelectItem value="20">20</SelectItem>
                            <SelectItem value="50">50</SelectItem>
                            <SelectItem value="100">100</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <p className="text-sm text-muted-foreground">
                    Showing <span className="font-medium text-foreground">{from}</span> to{" "}
                    <span className="font-medium text-foreground">{to}</span> of{" "}
                    <span className="font-medium text-foreground">{total}</span> results
                </p>
            </div>

            <div className="flex items-center space-x-2">
                <Button
                    variant="outline"
                    size="icon"
                    className="h-9 w-9 bg-background"
                    disabled={currentPage === 1}
                    onClick={() => navigate(1)}
                    aria-label="First page"
                >
                    <ChevronsLeft className="h-4 w-4"/>
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    className="h-9 w-9 bg-background"
                    disabled={currentPage === 1}
                    onClick={() => navigate(currentPage - 1)}
                    aria-label="Previous page"
                >
                    <ChevronLeft className="h-4 w-4"/>
                </Button>

                <span className="text-sm text-foreground mx-2">
                    Page <span className="font-medium">{currentPage}</span> of <span
                    className="font-medium">{lastPage}</span>
                </span>

                <Button
                    variant="outline"
                    size="icon"
                    className="h-9 w-9 bg-background"
                    disabled={currentPage === lastPage}
                    onClick={() => navigate(currentPage + 1)}
                    aria-label="Next page"
                >
                    <ChevronRight className="h-4 w-4"/>
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    className="h-9 w-9 bg-background"
                    disabled={currentPage === lastPage}
                    onClick={() => navigate(lastPage)}
                    aria-label="Last page"
                >
                    <ChevronsRight className="h-4 w-4"/>
                </Button>
            </div>
        </div>
    );
}
