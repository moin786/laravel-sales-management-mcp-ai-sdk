import { Head } from '@inertiajs/react';
import { AlertTriangle, Package, ReceiptText, WalletCards } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type Summary = { products: number; lowStock: number; orders: number; revenue: string };
const formatCurrency = (amount: string) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT' }).format(Number(amount));

export default function Dashboard({ summary }: { summary: Summary }) {
    const metrics = [
        { title: 'Products', value: summary.products, detail: 'Catalogued items', icon: Package },
        { title: 'Low stock', value: summary.lowStock, detail: 'Below 10 units', icon: AlertTriangle },
        { title: 'Orders', value: summary.orders, detail: 'All-time completed sales', icon: ReceiptText },
        { title: 'Revenue', value: formatCurrency(summary.revenue), detail: 'All-time sales revenue', icon: WalletCards },
    ];
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div><h1 className="text-2xl font-semibold tracking-tight">Sales overview</h1><p className="text-muted-foreground">Track inventory and completed sales from one place.</p></div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {metrics.map(({ title, value, detail, icon: Icon }) => <Card key={title} className="gap-3 py-5"><CardHeader className="flex-row items-center justify-between px-5"><CardTitle className="text-sm font-medium">{title}</CardTitle><Icon className="text-muted-foreground size-4" /></CardHeader><CardContent className="px-5"><p className="text-2xl font-semibold">{value}</p><CardDescription>{detail}</CardDescription></CardContent></Card>)}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
