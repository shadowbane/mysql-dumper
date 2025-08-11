import React from 'react';
import {format, parse, isValid} from 'date-fns';
import {Calendar, X} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {Popover, PopoverContent, PopoverTrigger} from '@/components/ui/popover';
import {Calendar as CalendarComponent} from '@/components/ui/calendar';

interface ExtendedDateRangeFilterProps {
    identifier: string;
    label: string;
    options?: Record<string, any>;
    onFilterChange: (identifier: string, value: [string | undefined, string | undefined] | null) => void;
    initialValue?: [string | undefined, string | undefined];
    resetSignal?: number;
}

export function DateRangeFilter({
                                    identifier,
                                    label,
                                    options = {},
                                    onFilterChange,
                                    initialValue,
                                    resetSignal,
                                }: ExtendedDateRangeFilterProps) {
    const [dateRange, setDateRange] = React.useState<{
        from: Date | undefined;
        to: Date | undefined;
    }>({
        from: undefined,
        to: undefined,
    });

    const [inputValues, setInputValues] = React.useState({
        from: '',
        to: ''
    });

    React.useEffect(() => {
        if (initialValue) {
            const [fromStr, toStr] = initialValue;
            setDateRange({
                from: fromStr ? new Date(fromStr) : undefined,
                to: toStr ? new Date(toStr) : undefined,
            });
            setInputValues({
                from: fromStr ? format(new Date(fromStr), 'yyyy-MM-dd') : '',
                to: toStr ? format(new Date(toStr), 'yyyy-MM-dd') : ''
            });
        }
    }, [initialValue]);

    React.useEffect(() => {
        if (resetSignal) {
            setDateRange({from: undefined, to: undefined});
            setInputValues({from: '', to: ''});
        }
    }, [resetSignal]);

    const handleDateRangeChange = (range: { from: Date | undefined; to: Date | undefined } | undefined) => {
        if (!range) {
            setDateRange({from: undefined, to: undefined});
            setInputValues({from: '', to: ''});
            onFilterChange(identifier, null);
            return;
        }

        const newRange = {
            from: range.from ? new Date(format(range.from, 'yyyy-MM-dd')) : undefined,
            to: range.to ? new Date(format(range.to, 'yyyy-MM-dd')) : undefined,
        };

        setDateRange(newRange);
        setInputValues({
            from: newRange.from ? format(newRange.from, 'yyyy-MM-dd') : '',
            to: newRange.to ? format(newRange.to, 'yyyy-MM-dd') : ''
        });

        if (newRange.from || newRange.to) {
            onFilterChange(identifier, [
                newRange.from ? format(newRange.from, 'yyyy-MM-dd') : undefined,
                newRange.to ? format(newRange.to, 'yyyy-MM-dd') : undefined
            ]);
        } else {
            onFilterChange(identifier, null);
        }
    };

    const handleInputChange = (field: 'from' | 'to', value: string) => {
        setInputValues(prev => ({...prev, [field]: value}));

        const date = parse(value, 'yyyy-MM-dd', new Date());
        if (isValid(date)) {
            const newRange = {
                ...dateRange,
                [field]: date
            };
            setDateRange(newRange);

            if (newRange.from || newRange.to) {
                onFilterChange(identifier, [
                    newRange.from ? format(newRange.from, 'yyyy-MM-dd') : undefined,
                    newRange.to ? format(newRange.to, 'yyyy-MM-dd') : undefined
                ]);
            }
        }
    };

    return (
        <div className="flex items-center gap-2">
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        className="w-[250px] justify-start text-left font-normal bg-background truncate"
                    >
                        <Calendar className="mr-2 h-4 w-4"/>
                        {dateRange.from ? (
                            dateRange.to ? (
                                <>
                                    {format(dateRange.from, "LLL dd, y")} -{" "}
                                    {format(dateRange.to, "LLL dd, y")}
                                </>
                            ) : (
                                format(dateRange.from, "LLL dd, y")
                            )
                        ) : (
                            <span>{label}</span>
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-4" align="start">
                    <div className="flex gap-2 mb-4">
                        <div className="grid gap-2">
                            <label className="text-sm text-muted-foreground">Start date</label>
                            <Input
                                type="date"
                                value={inputValues.from}
                                onChange={(e) => handleInputChange('from', e.target.value)}
                                className="w-[140px]"
                            />
                        </div>
                        <div className="grid gap-2">
                            <label className="text-sm text-muted-foreground">End date</label>
                            <Input
                                type="date"
                                value={inputValues.to}
                                onChange={(e) => handleInputChange('to', e.target.value)}
                                className="w-[140px]"
                            />
                        </div>
                    </div>
                    <CalendarComponent
                        initialFocus
                        mode="range"
                        defaultMonth={dateRange.from}
                        selected={{
                            from: dateRange.from,
                            to: dateRange.to,
                        }}
                        onSelect={handleDateRangeChange}
                        numberOfMonths={2}
                        {...options}
                    />
                </PopoverContent>
            </Popover>
            {(dateRange.from || dateRange.to) && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    onClick={() => handleDateRangeChange(undefined)}
                >
                    <X className="h-4 w-4"/>
                </Button>
            )}
        </div>
    );
}

import {clsx, type ClassValue} from 'clsx';
import {twMerge} from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
