export type ColumnType =
    'text'
    | 'number'
    | 'date'
    | 'datetime'
    | 'badge'
    | 'currency'
    | 'boolean'
    | 'color'
    | 'select';

export interface BaseColumnConfig {
    name: string;
    label: string;
    type?: ColumnType;
    sortable?: boolean;
    className?: string;
}

export interface TextColumnConfig extends BaseColumnConfig {
    type?: 'text';
    prefix?: string;
    suffix?: string;
    limit?: number;
    transform?: (value: any, row?: any) => string;
}

export interface DateColumnConfig extends BaseColumnConfig {
    type: 'date';
    format?: string;
    transform?: (value: any, row?: any) => string;
}

export interface DateTimeColumnConfig extends BaseColumnConfig {
    type: 'datetime';
    format?: string;
    showTimeZone?: boolean;
    transform?: (value: any, row?: any) => string;
}

export interface NumberColumnConfig extends BaseColumnConfig {
    type: 'number';
    decimals?: number;
    prefix?: string;
    suffix?: string;
    transform?: (value: any, row?: any) => string | number;
}

export interface CurrencyColumnConfig extends BaseColumnConfig {
    type: 'currency';
    currencyCode?: string;
    currencyField?: string; // Field name containing the currency code
    showCurrencyCode?: boolean;
    transform?: (value: any, row?: any) => string | number;
}

export interface BadgeColumnConfig extends BaseColumnConfig {
    type: 'badge';
    variant?: 'default' | 'secondary' | 'destructive' | 'outline';
    transform?: (value: any, row?: any) => string;
    transformVariant?: (value: any, row?: any) => string;
}

export interface BooleanColumnConfig extends BaseColumnConfig {
    type: 'boolean';
    trueLabel?: string;
    falseLabel?: string;
    transform?: (value: any, row?: any) => boolean;
}

export interface ColorColumnConfig extends BaseColumnConfig {
    type: 'color';
    showColorValue?: boolean; // Whether to show the color value (default: true)
    swatchSize?: 'sm' | 'md' | 'lg'; // Size of the color swatch
    transform?: (value: any, row?: any) => string;
}

export interface SelectFromArrayColumnConfig extends BaseColumnConfig {
    type: 'select';
    options: {
        data: Record<string | number, any>;  // The options data as key-value pairs
        displayCallback?: (value: any, row?: any) => string;  // Optional callback to format the display
        displayField?: string;  // Optional field name to display from the value object
        displayTemplate?: string; // Optional template like "{code} - {name}"
    };
    transform?: (value: any, row?: any) => string | number;
}

export type ColumnConfig =
    | TextColumnConfig
    | DateColumnConfig
    | DateTimeColumnConfig
    | NumberColumnConfig
    | CurrencyColumnConfig
    | BadgeColumnConfig
    | BooleanColumnConfig
    | ColorColumnConfig
    | SelectFromArrayColumnConfig;
