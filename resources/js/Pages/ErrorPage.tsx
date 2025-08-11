import React from 'react';
import {router} from '@inertiajs/react';
import {Button} from '@/components/ui/button';
import {XCircle, AlertCircle, Ban, ServerCrash} from 'lucide-react';

const ErrorPage = ({status, message, previousUrl}) => {
    const title = {
        503: '503: Service Unavailable',
        500: '500: Server Error',
        404: '404: Page Not Found',
        403: '403: Forbidden',
    }[status];

    const description = {
        503: 'Sorry, we are doing some maintenance. Please check back soon.',
        500: 'Whoops, something went wrong on our servers.',
        404: 'Sorry, the page you are looking for could not be found.',
        403: 'Sorry, you are forbidden from accessing this page.',
    }[status];

    const Icon = {
        503: ServerCrash,
        500: AlertCircle,
        404: XCircle,
        403: Ban,
    }[status];

    return (
        <div className="min-h-screen bg-background flex items-center justify-center p-4">
            <div className="text-center space-y-6 max-w-lg">
                <div className="space-y-2">
                    <Icon className="mx-auto h-12 w-12 text-destructive"/>
                    <h1 className="text-4xl font-bold tracking-tight">{title}</h1>
                    <p className="text-lg text-muted-foreground">{description}</p>
                    <p className="text-sm text-muted-foreground">{message}</p>
                </div>

                <div className="flex justify-center gap-4">
                    <Button
                        variant="outline"
                        onClick={() => router.visit('/dashboard')}
                    >
                        Go to Home
                    </Button>
                    <Button
                        onClick={() => router.visit(previousUrl)}
                    >
                        Try Again
                    </Button>
                </div>
            </div>
        </div>
    );
};

export default ErrorPage;
