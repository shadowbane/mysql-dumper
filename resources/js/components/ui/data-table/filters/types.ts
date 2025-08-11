import { ReactNode } from 'react';

export type FilterValue = string | number | boolean | Date | null | undefined;

export interface BaseFilterProps {
    identifier: string;
    label: string;
    onFilterChange?: (identifier: string, value: FilterValue | FilterValue[]) => void;
    resetSignal?: number; // Add this to trigger resets
}

export interface DateRangeFilterProps extends BaseFilterProps {
    type: 'date_range';
    options?: {
        timePicker?: boolean;
        timePickerIncrement?: number;
        showDropdowns?: boolean;
        minDate?: Date;
        maxDate?: Date;
    };
    initialValue?: [string | undefined, string | undefined];
}

export interface SelectFilterProps extends BaseFilterProps {
    type: 'select';
    options: { label: string; value: string | number }[];
    multiple?: boolean;
    initialValue?: string;
}

export type FilterProps = DateRangeFilterProps | SelectFilterProps;
