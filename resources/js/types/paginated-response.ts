/**
 * Generic interface for paginated API responses, reflecting a simpler pagination structure.
 * @template T The type of the items in the data array.
 */
export interface PaginatedResponse<T> {
    /** The array of data items for the current page. */
    data: T[];
    /** The current page number. */
    current_page: number;
    /** The last page number. */
    last_page: number;
    /** The total number of items across all pages. */
    total: number;
    /** The index of the first item on the current page. Null if no items. */
    from: number | null;
    /** The index of the last item on the current page. Null if no items. */
    to: number | null;
    /** The base path for the paginator. */
    path: string;
    /** The number of items per page. */
    per_page: number; // Added this as it's standard in Laravel pagination and useful
    /** Next page URL */
    next_page_url: string | null;
    /** Previous page URL */
    prev_page_url: string | null;
    /** First page URL */
    first_page_url: string | null;
    /** Last page URL */
    last_page_url: string | null;
    /** Optional page parameter name (for custom pagination) */
    page_name?: string | null;
}
