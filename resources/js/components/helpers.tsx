import { usePage } from '@inertiajs/react';

// Simple currency formatter - you can expand this as needed
export function formatCurrency(
    amount: number, 
    currency: string = 'USD', 
    showCurrencyCode: boolean = false
): string {
    const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount);
    
    if (showCurrencyCode) {
        return `${formatted} ${currency}`;
    }
    
    return formatted;
}

// Simple hook to get company settings from page props
export function useCompany(pageProps: any) {
    // This is a placeholder - you can implement proper company settings logic
    return {
        settings: {
            currency: 'USD',
            // Add other company settings as needed
        }
    };
}