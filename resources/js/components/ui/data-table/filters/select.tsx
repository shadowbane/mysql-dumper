import React from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {Button} from '@/components/ui/button';
import {X} from 'lucide-react';
import {SelectFilterProps} from './types';
import {cn} from '@/lib/utils'; // Import the utility function

interface ExtendedSelectFilterProps extends SelectFilterProps {
    initialValue?: string | string[];
    resetSignal?: number;
}

export function SelectFilter({
                                 identifier,
                                 label,
                                 options,
                                 multiple = false,
                                 onFilterChange,
                                 initialValue,
                                 resetSignal,
                             }: ExtendedSelectFilterProps) {
    const [value, setValue] = React.useState<string | string[]>(
        multiple ? (Array.isArray(initialValue) ? initialValue : []) : (initialValue || '')
    );

    React.useEffect(() => {
        if (initialValue) {
            setValue(
                multiple
                    ? Array.isArray(initialValue)
                        ? initialValue
                        : typeof initialValue === 'string'
                            ? [initialValue]
                            : []
                    : initialValue,
            );
        } else {
            // initialValue is falsy (null, undefined, empty string)
            // This means the filter should be cleared or set to its default empty state.
            // Empty array for multi-select, empty string for single
            setValue(multiple ? [] : '');
        }
    }, [initialValue, multiple]);

    // Listen for reset signal
    React.useEffect(() => {
        if (resetSignal) {
            setValue(multiple ? [] : '');
        }
    }, [resetSignal, multiple]);

    const handleChange = (newValue: string) => {
        if (multiple) {
            const newValues = Array.isArray(value) ? [...value] : [];
            const valueIndex = newValues.indexOf(newValue);

            if (valueIndex === -1) {
                newValues.push(newValue);
            } else {
                newValues.splice(valueIndex, 1);
            }

            setValue(newValues);
            onFilterChange(identifier, newValues.length > 0 ? newValues : null);
        } else {
            setValue(newValue);
            onFilterChange(identifier, newValue);
        }
    };

    const handleReset = () => {
        setValue(multiple ? [] : '');
        onFilterChange(identifier, null);
    };

    const getDisplayValue = () => {
        if (multiple && Array.isArray(value)) {
            if (value.length === 0) return label;
            if (value.length === 1) {
                const option = options.find(opt => opt.value.toString() === value[0]);
                return option ? option.label : label;
            }
            return `${value.length} selected`;
        }

        if (!Array.isArray(value) && value) {
            const option = options.find(opt => opt.value.toString() === value);
            return option ? option.label : label;
        }

        return label;
    };

    const isOptionSelected = (optionValue: string) => {
        if (multiple && Array.isArray(value)) {
            return value.includes(optionValue);
        }
        return value === optionValue;
    };

    return (
        <div className="flex items-center gap-2">
            <Select
                value={Array.isArray(value) ? value[0] || '' : value}
                onValueChange={handleChange}
            >
                <SelectTrigger className="w-[180px] bg-background">
                    <div className="flex items-center justify-between gap-2 overflow-hidden">
                        <SelectValue
                            placeholder={label}
                            className="truncate max-w-[160px]"
                        >
                            {getDisplayValue()}
                        </SelectValue>
                    </div>
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem
                            key={option.value.toString()}
                            value={option.value.toString()}
                            className={multiple && isOptionSelected(option.value.toString()) ? 'bg-accent' : ''}
                        >
                            <div className="flex items-center gap-2">
                                {multiple && (
                                    <div className={cn(
                                        "w-4 h-4 border rounded flex items-center justify-center",
                                        isOptionSelected(option.value.toString())
                                            ? "bg-primary border-primary"
                                            : "border-input"
                                    )}>
                                        {isOptionSelected(option.value.toString()) && (
                                            <span className="text-primary-foreground text-xs">✓</span>
                                        )}
                                    </div>
                                )}
                                {option.label}
                            </div>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {((multiple && Array.isArray(value) && value.length > 0) || (!multiple && value)) && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 -ml-1"
                    onClick={handleReset}
                >
                    <X className="h-4 w-4"/>
                </Button>
            )}
        </div>
    );
}
