import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getExpandedRowModel,
    getSortedRowModel,
    SortingState,
    useReactTable,
} from "@tanstack/react-table";
import {router} from "@inertiajs/react";
import React, {useState, useEffect, useMemo, useCallback} from "react";
import {
    Search,
    MoreHorizontal,
    Eye,
    Pencil,
    Trash,
    Plus,
    ChevronDown,
    ChevronRight,
    Ban,
    ArrowRight,
    Info,
    Loader2
} from "lucide-react";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {Input} from "@/components/ui/input";
import {Button} from "@/components/ui/button";
import {PaginationData} from "@/types/pagination";
import {Pagination} from "@/components/ui/pagination";
import {FilterProps, FilterValue, DateRangeFilter, SelectFilter} from "./data-table/filters";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {toast} from "sonner";
import {Textarea} from "@/components/ui/textarea";
import {Checkbox} from "@/components/ui/checkbox";
import {cn} from "@/lib/utils";
import {DatePicker} from "@/components/ui/date-picker";
import {Label} from "@/components/ui/label";

// Action configuration interfaces
type ActionConfig = {
    type: 'route' | 'command';
    label: string;
    action: string | ((row: any) => void);
    icon?: React.ReactNode;
    placement?: 'top' | 'inline';
    order?: 'beginning' | 'end';
};

interface TableActionsConfig<TData = any> {
    create?: ActionConfig;
    preview?: {
        enabled: boolean | ((row: TData) => boolean);
        baseUrl: string;
        onClick?: (row: TData) => void;
        label?: string;
    };
    edit?: {
        enabled: boolean | ((row: TData) => boolean);
        baseUrl?: string;
        onClick?: (row: TData) => void;
        label?: string;
        shouldShow?: (row: TData) => boolean;
    };
    delete?: {
        enabled: boolean | ((row: TData) => boolean);
        onClick?: (id: any, row: TData) => void;
        name?: string;
        value?: string | ((row: TData) => string);
        baseUrl?: string;
        label?: string;
        shouldShow?: (row: TData) => boolean;
    };
    cancel?: {
        enabled: boolean | ((row: TData) => boolean);
        onClick?: (id: any) => void;
        name?: string;
        value?: string | ((row: TData) => string);
        baseUrl?: string;
        label?: string;
        shouldShow?: (row: TData) => boolean;
    };
    additionalActions?: ActionConfig[];
}

interface RowClickConfig<TData> {
    enabled: boolean;
    action: 'route' | 'function';
    route?: string; // Base route for navigation
    routeParam?: string; // Parameter to append to route (usually 'id')
    onClick?: (row: TData) => void; // Custom function to call on row click
    stopPropagation?: boolean; // Whether to stop propagation to avoid triggering row click when clicking cells with actions
    excludeClickOnColumns?: string[]; // Array of column keys that should not trigger the row click
}

// Bulk actions configuration interfaces
interface BulkAction<TData> {
    id: string;
    label: string;
    icon?: React.ReactNode;
    variant?: 'default' | 'destructive' | 'outline' | 'secondary';
    action: (selectedItems: TData[], clearSelection: () => void, formData?: Record<string, any>) => Promise<void> | void;
    requiresConfirmation?: boolean;
    confirmationTitle?: string;
    confirmationDescription?: string;
    isDestructive?: boolean;
    isDisabled?: (selectedItems: TData[]) => boolean;
    shouldShow?: (selectedItems: TData[]) => boolean;
    requiresReason?: boolean; // For actions that need user input
    customFormFields?: Array<{
        type: 'date' | 'text' | 'textarea' | 'select';
        name: string;
        label: string;
        placeholder?: string;
        required?: boolean;
        defaultValue?: any;
        validate?: (value: any, selectedItems: TData[]) => string | undefined; // Return error message if invalid
    }>;
}

interface BulkActionsConfig<TData> {
    enabled: boolean;
    actions: BulkAction<TData>[];
    position?: 'top' | 'bottom' | 'both'; // Where to show bulk action bar
    confirmationRequired?: boolean; // Require confirmation for destructive actions
    loadingStates?: boolean; // Show loading states during bulk operations
}

interface SelectableConfig<TData> {
    enabled: boolean;
    keyField?: string; // Field to use as unique identifier (default: 'id')
    selectableCondition?: (row: TData) => boolean; // Condition for row selectability
    maxSelections?: number; // Optional limit on selections
    persistSelection?: boolean; // Persist selection in localStorage
    selectionInfo?: React.ReactNode;
}


// DataTable props interface
interface DataTableProps<TData, TValue> {
    pageTitle: string;
    columns: ColumnDef<TData, TValue>[];
    data: PaginationData;
    enableSearch?: boolean;
    filters?: FilterProps[];
    actions?: TableActionsConfig<TData>;
    expandable?: {
        enabled: boolean;
        renderExpanded: (row: TData) => React.ReactNode;
        hasExpandedContent?: (row: TData) => boolean;
    };
    rowClick?: RowClickConfig<TData>;
    selectable?: SelectableConfig<TData>;
    bulkActions?: BulkActionsConfig<TData>;
}

export function DataTable<TData extends Record<string, any>, TValue>({
                                                                         pageTitle,
                                                                         columns,
                                                                         data,
                                                                         enableSearch = false,
                                                                         filters = [],
                                                                         actions,
                                                                         expandable,
                                                                         rowClick,
                                                                         selectable,
                                                                         bulkActions,
                                                                     }: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [searchTerm, setSearchTerm] = useState("");
    const [activeFilters, setActiveFilters] = useState<Record<string, FilterValue | FilterValue[]>>({});
    const [initialized, setInitialized] = useState(false);
    const [expanded, setExpanded] = useState({});

    // Bulk actions state
    const [selectedRows, setSelectedRows] = useState<Set<string>>(new Set());
    const [selectedItemsData, setSelectedItemsData] = useState<Map<string, TData>>(new Map());
    const [bulkActionLoading, setBulkActionLoading] = useState<string | null>(null);
    const [selectAllState, setSelectAllState] = useState<'none' | 'some' | 'all'>('none');

    // Bulk action confirmation dialog state
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);
    const [pendingBulkAction, setPendingBulkAction] = useState<BulkAction<TData> | null>(null);
    const [pendingSelectedItems, setPendingSelectedItems] = useState<TData[]>([]);
    const [bulkActionFormData, setBulkActionFormData] = useState<Record<string, any>>({});
    const [bulkActionFormErrors, setBulkActionFormErrors] = useState<Record<string, string>>({});

    // Selection helper functions
    const getRowId = useCallback((row: TData): string => {
        const keyField = selectable?.keyField || 'id';
        return String(row[keyField]);
    }, [selectable?.keyField]);

    const getSelectedItems = useCallback((): TData[] => {
        // Return all selected items from the stored data map
        return Array.from(selectedItemsData.values());
    }, [selectedItemsData]);

    const clearSelection = useCallback(() => {
        setSelectedRows(new Set());
        setSelectedItemsData(new Map());
        setSelectAllState('none');
    }, []);

    const updateSelectAllState = useCallback((newSelection: Set<string>) => {
        const tableData = data.data as unknown as TData[];
        const selectableRowsOnPage = tableData.filter(row => {
            return selectable?.selectableCondition
                ? selectable.selectableCondition(row)
                : true;
        });

        const selectedOnPage = selectableRowsOnPage.filter(row =>
            newSelection.has(getRowId(row))
        );

        if (selectedOnPage.length === 0) {
            setSelectAllState('none');
        } else if (selectedOnPage.length === selectableRowsOnPage.length) {
            setSelectAllState('all');
        } else {
            setSelectAllState('some');
        }
    }, [data.data, selectable?.selectableCondition, getRowId]);

    // Handle bulk action execution
    const handleBulkAction = useCallback(async (action: BulkAction<TData>, selectedItems: TData[], formData?: Record<string, any>) => {
        try {
            setBulkActionLoading(action.id);
            await action.action(selectedItems, clearSelection, formData);
        } catch (error: any) {
            console.error(`Failed to ${action.label.toLowerCase()}: ${error.message}`);
        } finally {
            setBulkActionLoading(null);
        }
    }, [clearSelection]);

    // Cleanup bulk confirmation dialog
    const cleanupBulkConfirmDialog = useCallback(() => {
        setBulkConfirmOpen(false);
        setPendingBulkAction(null);
        setPendingSelectedItems([]);
        setBulkActionFormData({});
        setBulkActionFormErrors({});
    }, []);

    // Bulk Actions Bar Component
    const BulkActionsBar = useCallback(({position}: { position: 'top' | 'bottom' }) => {
        if (!bulkActions?.enabled || selectedRows.size === 0) return null;

        const shouldShow = position === 'top'
            ? bulkActions.position === 'top' || bulkActions.position === 'both'
            : bulkActions.position === 'bottom' || bulkActions.position === 'both';

        if (!shouldShow) return null;

        const selectedItems = getSelectedItems();

        return (
            <div className="flex items-center justify-between p-4 bg-accent/50 border-b border-border">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">
                        {selectedRows.size} item{selectedRows.size !== 1 ? 's' : ''} selected
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={clearSelection}
                        className="h-7 px-2 text-xs"
                    >
                        Clear selection
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    {bulkActions.actions.map((action) => {
                        const shouldShow = action.shouldShow ? action.shouldShow(selectedItems) : true;
                        const isDisabled = action.isDisabled ? action.isDisabled(selectedItems) : false;

                        if (!shouldShow) return null;

                        return (
                            <Button
                                key={action.id}
                                variant={action.variant || 'default'}
                                size="sm"
                                onClick={() => {
                                    if (action.requiresConfirmation || action.isDestructive) {
                                        // Initialize form data with default values
                                        const initialFormData: Record<string, any> = {};
                                        action.customFormFields?.forEach(field => {
                                            initialFormData[field.name] = field.defaultValue || '';
                                        });
                                        setBulkActionFormData(initialFormData);

                                        // Show confirmation dialog
                                        setPendingBulkAction(action);
                                        setPendingSelectedItems(selectedItems);
                                        setBulkConfirmOpen(true);
                                    } else {
                                        // Execute action directly
                                        handleBulkAction(action, selectedItems);
                                    }
                                }}
                                disabled={isDisabled || bulkActionLoading === action.id}
                                className={cn(
                                    action.isDestructive && "hover:bg-destructive hover:text-destructive-foreground"
                                )}
                            >
                                {bulkActionLoading === action.id ? (
                                    <Loader2 className="h-4 w-4 animate-spin mr-2"/>
                                ) : (
                                    action.icon && <span className="mr-2">{action.icon}</span>
                                )}
                                {action.label}
                            </Button>
                        );
                    })}
                </div>
            </div>
        );
    }, [bulkActions, selectedRows, getSelectedItems, clearSelection, bulkActionLoading]);

    // Initialize states from URL on mount
    useEffect(() => {
        if (!initialized) {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            const newFilters: Record<string, FilterValue | FilterValue[]> = {};
            // const pageParamName = data.page_name || 'page';

            // Initialize search term
            const searchParam = params.get('search');
            if (searchParam) {
                setSearchTerm(searchParam);
                newFilters.search = searchParam;
            }

            // Initialize filters
            filters.forEach(filter => {
                if (filter.type === 'date_range') {
                    const fromDate = params.get(`${filter.identifier}_from`);
                    const toDate = params.get(`${filter.identifier}_to`);
                    if (fromDate || toDate) {
                        newFilters[`${filter.identifier}_from`] = fromDate;
                        newFilters[`${filter.identifier}_to`] = toDate;
                    }
                } else if (filter.type === 'select' && filter.multiple) {
                    // For multi-select, check if the value contains commas (multiple values)
                    const value = params.get(filter.identifier);
                    if (value) {
                        // If the value contains commas, split it into an array
                        if (value.includes(',')) {
                            newFilters[filter.identifier] = value.split(',');
                        } else {
                            newFilters[filter.identifier] = [value];
                        }
                    }
                } else {
                    const value = params.get(filter.identifier);
                    if (value) {
                        newFilters[filter.identifier] = value;
                    }
                }
            });

            setActiveFilters(newFilters);

            // Initialize sorting
            const sortParam = params.get('sort');
            const directionParam = params.get('direction');
            if (sortParam) {
                setSorting([{
                    id: sortParam,
                    desc: directionParam === 'desc'
                }]);
            }

            setInitialized(true);
        }
    }, [initialized, filters, data.page_name]);

    // Delete dialog state
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [itemToDelete, setItemToDelete] = useState<TData | null>(null);
    const [processing, setProcessing] = useState(false);

    const cleanupDeleteAlert = () => {
        setProcessing(false);
        setDeleteModalOpen(false);
        setItemToDelete(null);
        // Reset body pointer-events
        setTimeout(() => (document.body.style.pointerEvents = ""), 500);
    };

    const [cancelReason, setCancelReason] = useState('');

    // Handle delete action
    const handleDelete = async (id: number) => {
        if (!actions?.delete?.baseUrl) return;

        setProcessing(true);
        router.delete(`${actions.delete.baseUrl}/${id}`, {
            preserveScroll: true,
            onError: (errors: any) => {
                const errorMessage = errors?.message || errors?.error || errors?.[0] || `An error occurred while deleting ${actions.delete?.name || 'item'}`;
                toast.error("Error", {
                    description: errorMessage,
                });
            },
            onSuccess: () => {
                toast.success(`${actions.delete?.name || 'Item'} deleted successfully`);
                cleanupDeleteAlert();
            },
            onFinish: () => {
                cleanupDeleteAlert();
            }
        });
    };

    const [cancelModalOpen, setCancelModalOpen] = useState(false);
    const [itemToCancel, setItemToCancel] = useState<TData | null>(null);
    const [cancelProcessing, setCancelProcessing] = useState(false);

    const cleanupCancelAlert = () => {
        setCancelProcessing(false);
        setCancelModalOpen(false);
        setItemToCancel(null);
        setCancelReason('');
        // Reset body pointer-events
        setTimeout(() => (document.body.style.pointerEvents = ""), 500);
    };

    const handleCancel = async (id: number) => {
        if (!actions?.cancel?.baseUrl) return;

        // Validate reason - minimum 3 characters as required by the backend
        if (!cancelReason || cancelReason.trim().length < 3) {
            toast.error("Error", {
                description: "Please provide a valid reason for cancellation (at least 3 characters).",
            });
            return;
        }

        setCancelProcessing(true);
        router.put(`${actions.cancel.baseUrl}/${id}/cancel`, {
            reason: cancelReason
        }, {
            preserveScroll: true,
            onError: (errors: any) => {
                const errorMessage = errors?.message || errors?.[0] || `An error occurred while cancelling ${actions.cancel?.name || 'item'}`;
                toast.error("Error", {
                    description: errorMessage,
                });
            },
            onSuccess: () => {
                toast.success(`${actions.cancel?.name || 'Item'} cancelled successfully`);
                cleanupCancelAlert();
            },
            onFinish: () => {
                cleanupCancelAlert();
            }
        });
    };

    // Create selection column if selectable is enabled
    const columnsWithSelection = useMemo(() => {
        if (!selectable?.enabled) return columns;

        const selectionColumn: ColumnDef<TData, any> = {
            id: 'select',
            header: () => (
                <div className="flex items-center">
                    <Checkbox
                        checked={selectAllState === 'all' ? true : selectAllState === 'some' ? 'indeterminate' as any : false}
                        onCheckedChange={(checked) => {
                            if (checked) {
                                // Select all selectable rows on current page
                                const tableData = data.data as unknown as TData[];
                                const selectableRows = tableData.filter(row => {
                                    return selectable?.selectableCondition
                                        ? selectable.selectableCondition(row)
                                        : true;
                                });

                                const newSelection = new Set(selectedRows);
                                const newDataMap = new Map(selectedItemsData);

                                selectableRows.forEach(row => {
                                    const rowId = getRowId(row);
                                    newSelection.add(rowId);
                                    newDataMap.set(rowId, row);
                                });

                                setSelectedRows(newSelection);
                                setSelectedItemsData(newDataMap);
                                setSelectAllState('all');
                            } else {
                                clearSelection();
                            }
                        }}
                        aria-label="Select all rows"
                    />
                </div>
            ),
            cell: ({row}) => {
                const isSelectable = selectable?.selectableCondition
                    ? selectable.selectableCondition(row.original)
                    : true;

                if (!isSelectable) return null;

                return (
                    <div className="flex items-center" data-no-row-click="true">
                        <Checkbox
                            checked={selectedRows.has(getRowId(row.original))}
                            onCheckedChange={(checked) => {
                                const rowId = getRowId(row.original);
                                const newSelection = new Set(selectedRows);
                                const newDataMap = new Map(selectedItemsData);

                                if (checked) {
                                    // Check max selections limit
                                    if (selectable?.maxSelections && newSelection.size >= selectable.maxSelections) {
                                        toast.error("Selection Limit Reached", {
                                            description: `You can only select up to ${selectable.maxSelections} items at once.`,
                                        });
                                        return;
                                    }
                                    newSelection.add(rowId);
                                    newDataMap.set(rowId, row.original);
                                } else {
                                    newSelection.delete(rowId);
                                    newDataMap.delete(rowId);
                                }

                                setSelectedRows(newSelection);
                                setSelectedItemsData(newDataMap);
                                updateSelectAllState(newSelection);
                            }}
                            aria-label={`Select row ${getRowId(row.original)}`}
                        />
                    </div>
                );
            },
            enableSorting: false,
            size: 40,
        };

        return [selectionColumn, ...columns];
    }, [columns, selectable, selectedRows, selectedItemsData, selectAllState, getRowId, clearSelection, updateSelectAllState]);

    // Create actions column if actions are provided
    const columnsWithActions = useMemo(() => {
        const inlineActions = actions?.additionalActions?.filter(action => action.placement === 'inline');

        // Check if there are any actions defined at all
        const hasAnyActionsDefined =
            actions?.delete ||
            actions?.preview ||
            actions?.edit ||
            actions?.cancel ||
            (inlineActions && inlineActions.length > 0);

        if (!hasAnyActionsDefined) return columnsWithSelection;

        // Check if any row would show at least one action
        const anyRowHasVisibleActions = (data.data as unknown as TData[]).some(row => {
            // Check standard actions
            const hasPreview = typeof actions?.preview?.enabled === 'function'
                ? actions.preview.enabled(row)
                : actions?.preview?.enabled;
            const hasEdit = typeof actions?.edit?.enabled === 'function'
                ? actions.edit.enabled(row)
                : actions?.edit?.enabled;
            const hasDelete = typeof actions?.delete?.enabled === 'function'
                ? actions.delete.enabled(row)
                : actions?.delete?.enabled;
            const hasCancel = typeof actions?.cancel?.enabled === 'function'
                ? actions.cancel.enabled(row)
                : actions?.cancel?.enabled;

            // Check additional inline actions
            const hasAdditionalActions = inlineActions?.some(action => {
                if (action.shouldShow) {
                    return action.shouldShow(row);
                }
                return true;
            });

            return (
                (hasPreview && (!actions.preview.shouldShow || actions.preview.shouldShow(row))) ||
                (hasEdit && (!actions.edit.shouldShow || actions.edit.shouldShow(row))) ||
                (hasDelete && (!actions.delete.shouldShow || actions.delete.shouldShow(row))) ||
                (hasCancel && (!actions.cancel.shouldShow || actions.cancel.shouldShow(row))) ||
                hasAdditionalActions
            );
        });

        // If no rows would show any actions, don't include the actions column
        if (!anyRowHasVisibleActions) return columnsWithSelection;

        const actionsColumn: ColumnDef<TData, any> = {
            id: 'actions',
            header: 'Actions',
            cell: ({row}) => {
                // Check if this specific row should show any actions
                const rowHasVisibleActions = (
                    (typeof actions?.preview?.enabled === 'function'
                        ? actions.preview.enabled(row.original)
                        : actions?.preview?.enabled) &&
                    (!actions?.preview.shouldShow || actions?.preview.shouldShow(row.original))
                ) || (
                    (typeof actions?.edit?.enabled === 'function'
                        ? actions.edit.enabled(row.original)
                        : actions?.edit?.enabled) &&
                    (!actions?.edit.shouldShow || actions?.edit.shouldShow(row.original))
                ) || (
                    (typeof actions?.delete?.enabled === 'function'
                        ? actions.delete.enabled(row.original)
                        : actions?.delete?.enabled) &&
                    (!actions?.delete.shouldShow || actions?.delete.shouldShow(row.original))
                ) || (
                    (typeof actions?.cancel?.enabled === 'function'
                        ? actions.cancel.enabled(row.original)
                        : actions?.cancel?.enabled) &&
                    (!actions?.cancel.shouldShow || actions?.cancel.shouldShow(row.original))
                ) || (
                    inlineActions?.some(action => {
                        if (action.shouldShow) {
                            return action.shouldShow(row.original);
                        }
                        return true;
                    })
                );

                if (!rowHasVisibleActions) return null;

                return (
                    <div data-no-row-click="true">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" className="h-8 w-8 p-0 hover:bg-accent/50">
                                    <span className="sr-only">Open menu</span>
                                    <MoreHorizontal className="h-4 w-4"/>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {actions?.additionalActions
                                    ?.filter(action => action.placement !== 'top' && action.order === 'beginning')
                                    .map((action, index) => (
                                        <DropdownMenuItem
                                            key={index}
                                            onClick={() => {
                                                if (action.type === 'route') {
                                                    router.visit(action.action as string);
                                                } else {
                                                    (action.action as (row: any) => void)(row.original);
                                                }
                                            }}
                                            className="hover:bg-accent hover:text-accent-foreground"
                                        >
                                            {action.icon && <span className="mr-2">{action.icon}</span>}
                                            {action.label}
                                        </DropdownMenuItem>
                                    ))}

                                {(typeof actions?.preview?.enabled === 'function'
                                    ? actions.preview.enabled(row.original)
                                    : actions?.preview?.enabled) && (
                                    <DropdownMenuItem
                                        onClick={() => {
                                            if (actions?.preview?.onClick) {
                                                actions?.preview.onClick(row.original);
                                            } else if (actions?.preview?.baseUrl) {
                                                router.visit(`${actions?.preview.baseUrl}/${row.original.id}`);
                                            }
                                        }}
                                        className="hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <Eye className="mr-2 h-4 w-4"/>
                                        {actions?.preview.label ?? "Preview"}
                                    </DropdownMenuItem>
                                )}

                                {(typeof actions?.edit?.enabled === 'function'
                                        ? actions.edit.enabled(row.original)
                                        : actions?.edit?.enabled) &&
                                    (!actions?.edit.shouldShow || actions?.edit.shouldShow(row.original)) && (
                                        <DropdownMenuItem
                                            onClick={() => {
                                                if (actions?.edit?.onClick) {
                                                    actions?.edit.onClick(row.original);
                                                } else if (actions?.edit?.baseUrl) {
                                                    router.visit(`${actions?.edit.baseUrl}/${row.original.id}/edit`);
                                                }
                                            }}
                                            className="hover:bg-accent hover:text-accent-foreground"
                                        >
                                            <Pencil className="mr-2 h-4 w-4"/>
                                            {actions?.edit.label ?? "Edit"}
                                        </DropdownMenuItem>
                                    )}

                                {(typeof actions?.cancel?.enabled === 'function'
                                        ? actions.cancel.enabled(row.original)
                                        : actions?.cancel?.enabled) &&
                                    (!actions.cancel.shouldShow || actions.cancel.shouldShow(row.original)) && (
                                        <DropdownMenuItem
                                            className="text-orange-500 hover:bg-orange-500/10 hover:text-orange-500 focus:bg-orange-500/10 focus:text-orange-500"
                                            onClick={() => {
                                                if (actions.cancel?.onClick) {
                                                    actions.cancel.onClick(row.original.id);
                                                } else {
                                                    setItemToCancel(row.original);
                                                    setCancelModalOpen(true);
                                                }
                                            }}
                                        >
                                            <Ban className="mr-2 h-4 w-4"/>
                                            {actions.cancel.label ?? "Cancel"}
                                        </DropdownMenuItem>
                                    )}

                                {(typeof actions?.delete?.enabled === 'function'
                                        ? actions.delete.enabled(row.original)
                                        : actions?.delete?.enabled) &&
                                    (!actions.delete.shouldShow || actions.delete.shouldShow(row.original)) && (
                                        <DropdownMenuItem
                                            className="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/20 hover:text-red-700 dark:hover:text-red-300 focus:bg-red-50 dark:focus:bg-red-500/20 focus:text-red-700 dark:focus:text-red-300"
                                            onClick={() => {
                                                if (actions.delete?.onClick) {
                                                    actions.delete.onClick(row.original.id, row.original);
                                                } else {
                                                    setItemToDelete(row.original);
                                                    setDeleteModalOpen(true);
                                                }
                                            }}
                                        >
                                            <Trash className="mr-2 h-4 w-4"/>
                                            {actions.delete.label ?? "Delete"}
                                        </DropdownMenuItem>
                                    )}

                                {actions?.additionalActions
                                    ?.filter(action => action.placement !== 'top' && action.order === 'end')
                                    .map((action, index) => (
                                        <DropdownMenuItem
                                            key={index}
                                            onClick={() => {
                                                if (action.type === 'route') {
                                                    router.visit(action.action as string);
                                                } else {
                                                    (action.action as (row: any) => void)(row.original);
                                                }
                                            }}
                                            className="hover:bg-accent hover:text-accent-foreground"
                                        >
                                            {action.icon && <span className="mr-2">{action.icon}</span>}
                                            {action.label}
                                        </DropdownMenuItem>
                                    ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        };

        return [...columnsWithSelection, actionsColumn];
    }, [columnsWithSelection, actions, data.data]);

    const columnsWithExpandable = useMemo(() => {
        if (!expandable?.enabled) return columnsWithActions;

        const expanderColumn: ColumnDef<TData, any> = {
            id: 'expander',
            header: () => null,
            cell: ({row}) => {
                // Only show expander if the row has expandable content
                const hasExpandableContent = expandable.hasExpandedContent ?
                    expandable.hasExpandedContent(row.original) : true;

                if (!hasExpandableContent) return null;

                return (
                    <div data-no-row-click="true">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                row.toggleExpanded();
                            }}
                            className="p-0 h-8 w-8 hover:bg-accent/50"
                        >
                            {row.getIsExpanded() ? (
                                <ChevronDown className="h-4 w-4"/>
                            ) : (
                                <ChevronRight className="h-4 w-4"/>
                            )}
                        </Button>
                    </div>
                );
            },
        };

        return [expanderColumn, ...columnsWithActions];
    }, [columnsWithActions, expandable]);

    const pageParamName = useMemo(() => data.page_name || 'page', [data.page_name]);

    const updateURL = useCallback((params: Record<string, any>) => {
        const url = new URL(window.location.href);
        // Check if this operation is solely a page change
        const isPageChangeOnly = Object.keys(params).length === 1 && pageParamName in params;

        if (!isPageChangeOnly) {
            // If filters, sort, or search are changing (not just a page navigation):
            // 1. Remove all existing URL search parameters except for the current page parameter.
            //    This is to clear out old filters/sort parameters.
            Array.from(url.searchParams.keys()).forEach(key => {
                if (key !== pageParamName) {
                    url.searchParams.delete(key);
                }
            });
            // 2. Reset to page 1, as filter/sort/search changes should typically start from the first page.
            url.searchParams.set(pageParamName, "1");
        }

        // 3. Apply all parameters passed in the `params` object.
        //    This will set new filter/sort/search values, or update the page number.
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.set(key, Array.isArray(value) ? value.join(',') : value.toString());
            } else {
                // If a param value is explicitly null/undefined/empty string, remove it from the URL.
                // This handles clearing specific filters if `params` contains e.g. { filter_name: "" }
                url.searchParams.delete(key);
            }
        });

        router.get(url.pathname + url.search.toString(), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [pageParamName]);

    const handleSortChange = (updater: any) => {
        const newSorting = typeof updater === 'function' ? updater(sorting) : updater;
        setSorting(newSorting);

        const newParams: Record<string, any> = {...activeFilters};

        if (searchTerm) {
            newParams.search = searchTerm;
        }

        const sort = newSorting[0];
        if (sort) {
            newParams.sort = sort.id;
            newParams.direction = sort.desc ? 'desc' : 'asc';
        } else {
            // If sorting is cleared, remove sort params
            delete newParams.sort;
            delete newParams.direction;
        }
        updateURL(newParams);
    };

    const table = useReactTable({
        data: data.data as unknown as TData[],
        columns: expandable?.enabled ? columnsWithExpandable : columnsWithActions,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        onSortingChange: handleSortChange,
        state: {
            sorting,
            expanded,
        },
        onExpandedChange: setExpanded,
        getExpandedRowModel: getExpandedRowModel(),
        manualSorting: true,
        enableMultiSort: false,
        initialState: {pagination: {pageSize: 10}},
    });

    const handleSearch = (value: string) => {
        setSearchTerm(value);
        const newParams: Record<string, any> = {...activeFilters};
        if (value) {
            newParams.search = value;
        } else {
            delete newParams.search;
        }
        const sort = sorting[0];
        if (sort) {
            newParams.sort = sort.id;
            newParams.direction = sort.desc ? 'desc' : 'asc';
        }
        updateURL(newParams);
    };

    const handleFilterChange = (identifier: string, value: FilterValue | FilterValue[] | null) => {
        const newFilters = {...activeFilters};

        const filterConfig = filters.find(f => f.identifier === identifier);

        // Remove old filter values first
        delete newFilters[identifier];
        if (filterConfig?.type === 'date_range') {
            delete newFilters[`${identifier}_from`];
            delete newFilters[`${identifier}_to`];
        }

        if (value !== null && value !== undefined && (Array.isArray(value) ? value.length > 0 : value !== '')) {
            if (filterConfig?.type === 'date_range' && Array.isArray(value)) {
                // Handle date range filter
                const [from, to] = value as [string?, string?];
                if (from) newFilters[`${identifier}_from`] = from;
                if (to) newFilters[`${identifier}_to`] = to;
            } else {
                newFilters[identifier] = value;
            }
        }

        setActiveFilters(newFilters);
        const paramsToUpdate: Record<string, any> = {...newFilters};
        if (searchTerm) {
            paramsToUpdate.search = searchTerm;
        }
        const sort = sorting[0];
        if (sort) {
            paramsToUpdate.sort = sort.id;
            paramsToUpdate.direction = sort.desc ? 'desc' : 'asc';
        }
        updateURL(paramsToUpdate);
    }

    const [resetSignal, setResetSignal] = useState(0);
    const handleResetAll = () => {
        setSearchTerm("");
        setActiveFilters({});
        setSorting([]);
        setResetSignal(prev => prev + 1);
        updateURL({});
    };

    // Keyboard navigation for pagination
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const activeElement = document.activeElement;
            const targetTagName = activeElement?.tagName;

            // Ignore keydown if an input, textarea, or select element is focused
            if (targetTagName === 'INPUT' || targetTagName === 'TEXTAREA' || targetTagName === 'SELECT') {
                return;
            }

            if (event.key === 'ArrowLeft') {
                if (data.current_page > 1) {
                    const newPage = data.current_page - 1;
                    updateURL({[pageParamName]: newPage.toString()});
                }
            } else if (event.key === 'ArrowRight') {
                if (data.current_page < data.last_page) {
                    const newPage = data.current_page + 1;
                    updateURL({[pageParamName]: newPage.toString()});
                }
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [data.current_page, data.last_page, pageParamName, updateURL]);


    const renderFilter = (filter: FilterProps) => {
        // Get initial value for the filter
        let initialValue: any = null;
        if (filter.type === 'date_range') {
            const fromDate = activeFilters[`${filter.identifier}_from`];
            const toDate = activeFilters[`${filter.identifier}_to`];
            if (fromDate || toDate) {
                initialValue = [fromDate, toDate];
            }
        } else {
            initialValue = activeFilters[filter.identifier];
        }

        switch (filter.type) {
            case 'date_range':
                return (
                    <DateRangeFilter
                        key={filter.identifier}
                        {...filter}
                        initialValue={initialValue}
                        onFilterChange={handleFilterChange}
                        resetSignal={resetSignal}
                    />
                );
            case 'select':
                return (
                    <SelectFilter
                        key={filter.identifier}
                        {...filter}
                        initialValue={initialValue}
                        onFilterChange={handleFilterChange}
                        resetSignal={resetSignal}
                    />
                );
            default:
                return null;
        }
    };

    return (
        <div className="w-full space-y-4">
            <div className="flex justify-between items-center">
                <h2 className="text-2xl font-bold">{pageTitle}</h2>
                {/* Header Actions */}
                {(() => {
                    if (actions) {
                        const topActions = actions.additionalActions?.filter(action => action.placement === 'top') || [];
                        const hasCreateAction = actions.create;

                        if (hasCreateAction || topActions.length > 0) {
                            return (
                                <div className="flex gap-2">
                                    {/* Beginning order top actions */}
                                    {topActions
                                        .filter(action => action.order === 'beginning')
                                        .map((action, index) => (
                                            <Button
                                                key={`top-action-beginning-${index}`}
                                                variant="default"
                                                onClick={() => {
                                                    if (action.type === 'route') {
                                                        router.visit(action.action as string);
                                                    } else {
                                                        (action.action as () => void)();
                                                    }
                                                }}
                                            >
                                                {action.icon && <span className="mr-2">{action.icon}</span>}
                                                {action.label}
                                            </Button>
                                        ))}

                                    {/* Create button */}
                                    {hasCreateAction && (
                                        <Button
                                            variant="default"
                                            onClick={() => {
                                                if (actions?.create?.type === 'route') {
                                                    router.visit(actions?.create?.action as string);
                                                } else {
                                                    (actions?.create?.action as () => void)();
                                                }
                                            }}
                                        >
                                            {actions?.create?.icon || <Plus className="h-4 w-4 mr-2"/>}
                                            {actions?.create?.label}
                                        </Button>
                                    )}

                                    {/* End order top actions */}
                                    {topActions
                                        .filter(action => action.order === 'end' || !action.order)
                                        .map((action, index) => (
                                            <Button
                                                key={`top-action-end-${index}`}
                                                variant="default"
                                                onClick={() => {
                                                    if (action.type === 'route') {
                                                        router.visit(action.action as string);
                                                    } else {
                                                        (action.action as () => void)();
                                                    }
                                                }}
                                            >
                                                {action.icon && <span className="mr-2">{action.icon}</span>}
                                                {action.label}
                                            </Button>
                                        ))}
                                </div>
                            );
                        }

                        return null;
                    }

                    return null;
                })()}
            </div>

            {/* Table */}
            <div className="container mx-auto">
                <div className="space-y-4">
                    {/* Search and Filters Bar */}
                    <div className="flex items-center gap-2 border-b border-border pb-4">
                        {enableSearch && (
                            <div className="relative flex-1">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground"/>
                                <Input
                                    placeholder="Search..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    onBlur={(e) => handleSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            handleSearch((e.target as HTMLInputElement).value);
                                        }
                                    }}
                                    className="pl-8 bg-background"
                                />
                            </div>
                        )}
                        {filters.length > 0 && (
                            <div className="flex items-center gap-2">
                                {filters.map(renderFilter)}
                            </div>
                        )}
                        {/* Reset All Button */}
                        {(searchTerm || Object.keys(activeFilters).length > 0 || sorting.length > 0) && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleResetAll}
                                className="ml-2"
                            >
                                Reset All
                            </Button>
                        )}

                    </div>

                    {/* Selection Info */}
                    {selectable?.enabled && selectable.selectionInfo && (
                        <div
                            className="px-4 py-2 bg-blue-50 border-b border-blue-200 text-sm text-blue-800 rounded-t-lg">
                            {selectable.selectionInfo}
                        </div>
                    )}

                    {/* Bulk Actions Bar - Top */}
                    <BulkActionsBar position="top"/>

                    <div className="rounded-md border border-border">
                        <Table>
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers.map((header) => (
                                            <TableHead key={header.id}>
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(
                                                        header.column.columnDef.header,
                                                        header.getContext(),
                                                    )}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows?.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <React.Fragment key={row.id}>
                                            <TableRow
                                                data-state={row.getIsSelected() && "selected"}
                                                className={cn(
                                                    rowClick?.enabled && "group cursor-pointer transition-colors hover:bg-accent/30",
                                                    expandable?.enabled && row.getIsExpanded() && "bg-accent/20"
                                                )}
                                                {...(rowClick?.enabled ? {
                                                    role: "button",
                                                    tabIndex: 0,
                                                    "aria-label": `Preview`
                                                } : {})}
                                                onClick={(e) => {
                                                    // Only handle row clicks if rowClick is enabled
                                                    if (!rowClick?.enabled) return;

                                                    // Check if click was on a column that should be excluded
                                                    if (rowClick.excludeClickOnColumns) {
                                                        const targetColumn = (e.target as HTMLElement).closest('[data-column-id]');
                                                        if (targetColumn && rowClick.excludeClickOnColumns.includes(targetColumn.getAttribute('data-column-id') || '')) {
                                                            return;
                                                        }
                                                    }

                                                    // Check if we should stop propagation for certain elements
                                                    if (rowClick.stopPropagation) {
                                                        if ((e.target as HTMLElement).closest('button') ||
                                                            (e.target as HTMLElement).closest('[data-no-row-click]')) {
                                                            return;
                                                        }
                                                    }

                                                    // Handle the row click based on the action type
                                                    if (rowClick.action === 'route' && rowClick.route) {
                                                        const id = rowClick.routeParam ? row.original[rowClick.routeParam] : row.original.id;
                                                        router.visit(`${rowClick.route}/${id}`);
                                                    } else if (rowClick.action === 'function' && rowClick.onClick) {
                                                        rowClick.onClick(row.original);
                                                    }
                                                }}
                                            >
                                                {row.getVisibleCells().map((cell, cellIndex) => {
                                                    // Determine if this is the visual indicator cell (first non-expander, non-selection cell)
                                                    let firstRegularCellIndex = 0;
                                                    if (expandable?.enabled) firstRegularCellIndex++;
                                                    if (selectable?.enabled) firstRegularCellIndex++;

                                                    const isFirstRegularCell = cellIndex === firstRegularCellIndex;

                                                    return (
                                                        <TableCell
                                                            key={cell.id}
                                                            data-column-id={cell.column.id}
                                                            className={cn(
                                                                // Add padding and position styling for the first regular cell (not expander)
                                                                rowClick?.enabled && isFirstRegularCell && "pl-10 relative",
                                                            )}
                                                        >
                                                            {/* Add a visual indicator for clickable rows - only in the first non-expander cell */}
                                                            {rowClick?.enabled && isFirstRegularCell && (
                                                                <div
                                                                    className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/50 opacity-0 transition-all group-hover:opacity-100 group-hover:text-primary">
                                                                    <ArrowRight className="h-4 w-4"/>
                                                                </div>
                                                            )}

                                                            {flexRender(
                                                                cell.column.columnDef.cell,
                                                                cell.getContext(),
                                                            )}
                                                        </TableCell>
                                                    );
                                                })}
                                            </TableRow>
                                            {/* Add expanded row content */}
                                            {expandable?.enabled && row.getIsExpanded() && (
                                                <TableRow className="bg-accent/10">
                                                    <TableCell colSpan={row.getVisibleCells().length}
                                                               className="p-0">
                                                        {expandable.renderExpanded(row.original)}
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </React.Fragment>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={columns.length + (expandable?.enabled ? 1 : 0) + (columnsWithActions.length > columns.length ? 1 : 0)}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            No results.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Bulk Actions Bar - Bottom */}
                    <BulkActionsBar position="bottom"/>

                    {/* Delete Confirmation Dialog */}
                    {actions?.delete?.enabled && (
                        <AlertDialog
                            open={deleteModalOpen}
                            onOpenChange={(open) => {
                                if (!processing) {
                                    setDeleteModalOpen(open);
                                    if (!open) {
                                        cleanupDeleteAlert();
                                    }
                                }
                            }}
                        >
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Are you sure you want to delete this {actions.delete?.name || 'item'}
                                        {actions.delete?.value && itemToDelete ?
                                            ` "${typeof actions.delete.value === 'function' ? actions.delete.value(itemToDelete) : itemToDelete[actions.delete.value]}"` :
                                            ''}?
                                        This action cannot be undone.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel disabled={processing}
                                                       onClick={cleanupDeleteAlert}>Cancel</AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() => itemToDelete && handleDelete(itemToDelete.id)}
                                        disabled={processing}
                                        className="bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 text-white"
                                    >
                                        {processing ? 'Deleting...' : 'Delete'}
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    )}

                    {/* Cancel Confirmation Dialog */}
                    {actions?.cancel?.enabled && (
                        <AlertDialog
                            open={cancelModalOpen}
                            onOpenChange={(open) => {
                                if (!cancelProcessing) {
                                    setCancelModalOpen(open);
                                    if (!open) {
                                        cleanupCancelAlert();
                                    }
                                }
                            }}
                        >
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Are you sure you want to cancel this {actions.cancel?.name || 'item'}
                                        {actions.cancel?.value && itemToCancel ?
                                            ` "${typeof actions.cancel.value === 'function' ? actions.cancel.value(itemToCancel) : itemToCancel[actions.cancel.value]}"` :
                                            ''}
                                        ? This action cannot be undone.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>

                                {/* Add the reason input field */}
                                <div className="my-4">
                                    <label
                                        htmlFor="cancel-reason"
                                        className="block text-sm font-medium text-foreground mb-1"
                                    >
                                        Reason for cancellation (required)
                                    </label>
                                    <Textarea
                                        id="cancel-reason"
                                        value={cancelReason}
                                        onChange={(e) => setCancelReason(e.target.value)}
                                        placeholder="Please provide a reason for cancellation"
                                        className="w-full"
                                        disabled={cancelProcessing}
                                    />
                                    {cancelReason.trim().length > 0 && cancelReason.trim().length < 3 && (
                                        <p className="text-sm text-red-500 mt-1">
                                            Reason must be at least 3 characters long
                                        </p>
                                    )}
                                </div>

                                <AlertDialogFooter>
                                    <AlertDialogCancel
                                        disabled={cancelProcessing}
                                        onClick={cleanupCancelAlert}
                                    >
                                        Keep
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() => itemToCancel && handleCancel(itemToCancel.id)}
                                        disabled={cancelProcessing || !cancelReason || cancelReason.trim().length < 3}
                                        className="bg-orange-500 hover:bg-orange-600 text-white"
                                    >
                                        {cancelProcessing ? 'Cancelling...' : 'Cancel'}
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    )}

                    {/* Bulk Action Confirmation Dialog */}
                    {bulkActions?.enabled && (
                        <AlertDialog
                            open={bulkConfirmOpen}
                            onOpenChange={(open) => {
                                if (!bulkActionLoading) {
                                    setBulkConfirmOpen(open);
                                    if (!open) {
                                        cleanupBulkConfirmDialog();
                                    }
                                }
                            }}
                        >
                            <AlertDialogContent className="max-w-lg">
                                <AlertDialogHeader className="space-y-2">
                                    <AlertDialogTitle className="text-lg font-semibold">
                                        {pendingBulkAction?.confirmationTitle || 'Confirm Action'}
                                    </AlertDialogTitle>
                                    <AlertDialogDescription
                                        className="text-sm text-muted-foreground leading-relaxed">
                                        {pendingBulkAction?.confirmationDescription ||
                                            `Are you sure you want to ${pendingBulkAction?.label.toLowerCase()} ${pendingSelectedItems.length} item${pendingSelectedItems.length !== 1 ? 's' : ''}?`}
                                        {pendingBulkAction?.isDestructive && (
                                            <span className="block mt-2 text-sm text-red-600 font-medium">
                                                This action cannot be undone.
                                            </span>
                                        )}
                                    </AlertDialogDescription>
                                </AlertDialogHeader>

                                {/* Divider */}
                                <hr className="my-4 border-border"/>

                                {/* Custom form fields */}
                                {pendingBulkAction?.customFormFields?.length > 0 && (
                                    <div className="space-y-4">
                                        {pendingBulkAction.customFormFields.map((field) => (
                                            <div key={field.name} className="space-y-1">
                                                <Label htmlFor={field.name} className="text-sm font-medium mr-3">
                                                    {field.label}
                                                    {field.required && <span className="text-red-500 ml-1">*</span>}
                                                </Label>
                                                {field.type === 'date' ? (
                                                    <DatePicker
                                                        value={bulkActionFormData[field.name] ? new Date(bulkActionFormData[field.name]) : undefined}
                                                        onChange={(date) => {
                                                            const dateValue = date
                                                                ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
                                                                : '';

                                                            setBulkActionFormData((prev) => ({
                                                                ...prev,
                                                                [field.name]: dateValue,
                                                            }));

                                                            // Validate on change
                                                            if (field.validate && dateValue) {
                                                                const error = field.validate(dateValue, pendingSelectedItems);
                                                                setBulkActionFormErrors((prev) => ({
                                                                    ...prev,
                                                                    [field.name]: error || '',
                                                                }));
                                                            } else {
                                                                setBulkActionFormErrors((prev) => ({
                                                                    ...prev,
                                                                    [field.name]: '',
                                                                }));
                                                            }
                                                        }}
                                                    />
                                                ) : field.type === 'textarea' ? (
                                                    <Textarea
                                                        id={field.name}
                                                        value={bulkActionFormData[field.name] || ''}
                                                        onChange={(e) => {
                                                            setBulkActionFormData((prev) => ({
                                                                ...prev,
                                                                [field.name]: e.target.value,
                                                            }));
                                                        }}
                                                        placeholder={field.placeholder}
                                                        className="w-full border-muted rounded-md px-3 py-2 focus:ring-2 focus:ring-primary focus:outline-none"
                                                    />
                                                ) : (
                                                    <Input
                                                        id={field.name}
                                                        type={field.type}
                                                        value={bulkActionFormData[field.name] || ''}
                                                        onChange={(e) => {
                                                            setBulkActionFormData((prev) => ({
                                                                ...prev,
                                                                [field.name]: e.target.value,
                                                            }));
                                                        }}
                                                        placeholder={field.placeholder}
                                                        className="w-full border-muted rounded-md px-3 py-2 focus:ring-2 focus:ring-primary focus:outline-none"
                                                    />
                                                )}

                                                {bulkActionFormErrors[field.name] && (
                                                    <p className="text-sm text-red-500 mt-1">{bulkActionFormErrors[field.name]}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <AlertDialogFooter className="mt-6 flex justify-end gap-2">
                                    <AlertDialogCancel
                                        disabled={!!bulkActionLoading}
                                        onClick={cleanupBulkConfirmDialog}
                                        className="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted"
                                    >
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={async () => {
                                            if (pendingBulkAction) {
                                                const validationErrors: Record<string, string> = {};
                                                let hasErrors = false;

                                                pendingBulkAction.customFormFields?.forEach((field) => {
                                                    const value = bulkActionFormData[field.name];

                                                    if (field.required && !value) {
                                                        validationErrors[field.name] = `${field.label} is required`;
                                                        hasErrors = true;
                                                    }

                                                    if (value && field.validate) {
                                                        const error = field.validate(value, pendingSelectedItems);
                                                        if (error) {
                                                            validationErrors[field.name] = error;
                                                            hasErrors = true;
                                                        }
                                                    }
                                                });

                                                setBulkActionFormErrors(validationErrors);
                                                if (hasErrors) return;

                                                await handleBulkAction(pendingBulkAction, pendingSelectedItems, bulkActionFormData);
                                                cleanupBulkConfirmDialog();
                                            }
                                        }}
                                        disabled={
                                            !!bulkActionLoading ||
                                            Object.values(bulkActionFormErrors).some((error) => !!error)
                                        }
                                        className={cn(
                                            "rounded-md px-4 py-2 text-sm font-medium",
                                            pendingBulkAction?.isDestructive
                                                ? "bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 text-white"
                                                : "bg-primary text-white hover:bg-primary/90"
                                        )}
                                    >
                                        {bulkActionLoading === pendingBulkAction?.id ? (
                                            <>
                                                <Loader2 className="h-4 w-4 animate-spin mr-2"/>
                                                Processing...
                                            </>
                                        ) : (
                                            pendingBulkAction?.label || 'Confirm'
                                        )}
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    )}

                    {/* Pagination */}
                    <div className="mt-4">
                        <Pagination
                            currentPage={data.current_page}
                            lastPage={data.last_page}
                            total={data.total}
                            from={data.from}
                            to={data.to}
                            perPage={data.per_page}
                            pageName={pageParamName}
                        />

                        {/* Pro Tip for Keyboard Navigation */}
                        {data.last_page > 1 && (
                            <div
                                className="flex justify-end pb-2"> {/* Aligns hint to the right, adds some vertical spacing */}
                                <div
                                    className="flex items-center gap-1.5 text-xs text-muted-foreground"> {/* Compact styling for the tip */}
                                    <Info className="h-4 w-4 text-blue-500"/> {/* Info icon */}
                                    <span>Pro tip: Navigate with</span>
                                    <kbd
                                        className="pointer-events-none ml-1 inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground opacity-100">
                                        ←
                                    </kbd>
                                    <kbd
                                        className="pointer-events-none inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground opacity-100">
                                        →
                                    </kbd>
                                    <span className="ml-0.5">keys</span>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}