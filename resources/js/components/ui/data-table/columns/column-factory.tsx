import { ColumnDef } from "@tanstack/react-table";
import { format } from "date-fns";
import { Badge } from "@/components/ui/badge";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { cn } from "@/lib/utils";
import {
    BadgeColumnConfig,
    BooleanColumnConfig,
    ColorColumnConfig,
    ColumnConfig,
    CurrencyColumnConfig,
    DateColumnConfig,
    DateTimeColumnConfig,
    NumberColumnConfig,
    SelectFromArrayColumnConfig,
    TextColumnConfig,
} from "@/components/ui/data-table/columns/types";
import { formatCurrency } from "@/components/helpers";

export function createDynamicColumns<T extends Record<string, any>>(
    configs: ColumnConfig[],
): ColumnDef<T>[] {
    return configs.map((config) => {
        const baseColumn: Partial<ColumnDef<T>> = {
            accessorKey: config.name,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={config.label} />
            ),
            enableSorting: config.sortable ?? false,
        };

        switch (config.type) {
            case "date":
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        if (!value) return null;

                        const dateConfig = config as DateColumnConfig;

                        // Apply transform if provided
                        if (dateConfig.transform) {
                            return dateConfig.transform(value, row.original);
                        }

                        return format(
                            new Date(value),
                            dateConfig.format || "MMM d, yyyy",
                        );
                    },
                };

            case "datetime":
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        if (!value) return null;

                        const dateTimeConfig = config as DateTimeColumnConfig;

                        // Apply transform if provided
                        if (dateTimeConfig.transform) {
                            return dateTimeConfig.transform(
                                value,
                                row.original,
                            );
                        }

                        const {
                            format: dateFormat = "MMM d, yyyy HH:mm",
                            showTimeZone,
                        } = dateTimeConfig;
                        const formattedDate = format(
                            new Date(value),
                            dateFormat,
                        );

                        if (showTimeZone) {
                            const timeZone = new Date(value)
                                .toLocaleTimeString("en-US", {
                                    timeZoneName: "short",
                                })
                                .split(" ")[2];
                            return `${formattedDate} ${timeZone}`;
                        }

                        return formattedDate;
                    },
                };

            case "number":
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        const numberConfig = config as NumberColumnConfig;

                        // Apply transform if provided
                        if (numberConfig.transform) {
                            return (
                                <div
                                    className={cn(
                                        "font-mono",
                                        config.className,
                                    )}
                                >
                                    {numberConfig.transform(
                                        value,
                                        row.original,
                                    )}
                                </div>
                            );
                        }

                        const { prefix, suffix, decimals } = numberConfig;
                        const formattedValue = Number(value).toLocaleString(
                            undefined,
                            {
                                minimumFractionDigits: 0, // Show at least 0 decimal places
                                maximumFractionDigits: decimals, // Show up to the configured decimals
                            },
                        );
                        return (
                            <div className={cn("font-mono", config.className)}>
                                {prefix}
                                {formattedValue}
                                {suffix}
                            </div>
                        );
                    },
                };

            case "currency":
                return {
                    ...baseColumn,
                    header: ({ column }) => (
                        <div className="text-right">
                            <DataTableColumnHeader
                                column={column}
                                title={config.label}
                            />
                        </div>
                    ),
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        const currencyConfig = config as CurrencyColumnConfig;

                        // Apply transform if provided
                        if (currencyConfig.transform) {
                            return (
                                <div
                                    className={cn(
                                        "text-right font-mono",
                                        config.className,
                                    )}
                                >
                                    {currencyConfig.transform(
                                        value,
                                        row.original,
                                    )}
                                </div>
                            );
                        }

                        const {
                            currencyCode,
                            currencyField,
                            showCurrencyCode,
                        } = currencyConfig;

                        // Get currency code from the data if currencyField is specified
                        const actualCurrencyCode = currencyField
                            ? (row.original as any)[currencyField]
                            : currencyCode || "USD";

                        return (
                            <div
                                className={cn(
                                    "text-right font-mono",
                                    config.className,
                                )}
                            >
                                {formatCurrency(
                                    Number(value),
                                    actualCurrencyCode,
                                    showCurrencyCode,
                                )}
                            </div>
                        );
                    },
                };

            case "badge":
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        const badgeConfig = config as BadgeColumnConfig;
                        const { variant, transform, transformVariant } =
                            badgeConfig;

                        // Transform the value if transform function is provided
                        const displayValue = transform
                            ? transform(value, row.original)
                            : value;
                        const badgeVariant = transformVariant
                            ? transformVariant(value, row.original)
                            : variant;

                        return (
                            <Badge
                                variant={badgeVariant || "secondary"}
                                className={config.className}
                            >
                                {displayValue}
                            </Badge>
                        );
                    },
                };

            case "boolean":
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        const booleanConfig = config as BooleanColumnConfig;

                        // Apply transform if provided
                        if (booleanConfig.transform) {
                            const transformedValue = booleanConfig.transform(
                                value,
                                row.original,
                            );
                            return transformedValue
                                ? booleanConfig.trueLabel || "Yes"
                                : booleanConfig.falseLabel || "No";
                        }

                        const { trueLabel, falseLabel } = booleanConfig;
                        return value ? trueLabel || "Yes" : falseLabel || "No";
                    },
                };

            case "color":
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        const colorConfig = config as ColorColumnConfig;
                        const {
                            showColorValue = true,
                            swatchSize = "md",
                            transform,
                        } = colorConfig;

                        // Apply transform if provided
                        const displayValue = transform
                            ? transform(value, row.original)
                            : value;

                        const swatchSizes = {
                            sm: "w-3 h-3",
                            md: "w-4 h-4",
                            lg: "w-5 h-5",
                        };

                        return (
                            <div
                                className={cn(
                                    "flex items-center",
                                    config.className,
                                )}
                            >
                                <div
                                    className={cn(
                                        swatchSizes[swatchSize],
                                        "rounded-full mr-2",
                                    )}
                                    style={{
                                        backgroundColor: displayValue as string,
                                    }}
                                />
                                {showColorValue && displayValue}
                            </div>
                        );
                    },
                };

            case "select":
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        let value = row.getValue(config.name);
                        const selectConfig =
                            config as SelectFromArrayColumnConfig;
                        const { options, transform } = selectConfig;

                        // Apply transform if provided
                        if (transform) {
                            const transformedValue = transform(
                                value,
                                row.original,
                            );
                            if (!options.data[transformedValue])
                                return transformedValue;
                            value = transformedValue;
                        }

                        if (value === null) return "-";

                        const option = options.data[value];
                        if (!option) return value;

                        if (options.displayCallback) {
                            return options.displayCallback(
                                option,
                                row.original,
                            );
                        }

                        if (options.displayTemplate) {
                            return options.displayTemplate.replace(
                                /{(\w+)}/g,
                                (match, field) => {
                                    return option[field] || match;
                                },
                            );
                        }

                        if (options.displayField) {
                            return option[options.displayField];
                        }

                        return String(option);
                    },
                };

            case "text":
            default:
                return {
                    ...baseColumn,
                    cell: ({ row }) => {
                        const value = row.getValue(config.name);
                        const textConfig = config as TextColumnConfig;
                        const { prefix, suffix, limit, transform } = textConfig;
                        let displayValue =
                            value === null || value === undefined
                                ? "-"
                                : String(value);

                        // Apply transform if provided
                        if (transform && value !== null) {
                            displayValue = transform(value, row.original);
                        }

                        if (limit && displayValue.length > limit) {
                            displayValue = `${displayValue.substring(0, limit)}...`;
                        }

                        return (
                            <div className={config.className}>
                                {prefix}
                                {displayValue}
                                {suffix}
                            </div>
                        );
                    },
                };
        }
    });
}