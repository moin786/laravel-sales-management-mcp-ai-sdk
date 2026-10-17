import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index, store } from '@/routes/sales';

type Product = { id: number; name: string; price: string; stock: number };
type Sale = { id: number; total: string; created_at: string; items: { id: number; quantity: number; product: { id: number; name: string } }[] };
const formatCurrency = (amount: string) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT' }).format(Number(amount));

export default function Sales({ products, sales }: { products: Product[]; sales: Sale[] }) {
    const form = useForm({ items: [{ product_id: '', quantity: '1' }] });
    const item = form.data.items[0];
    function submit(event: FormEvent<HTMLFormElement>) { event.preventDefault(); form.submit(store(), { onSuccess: () => form.reset() }); }

    return <><Head title="Sales" /><div className="flex flex-1 flex-col gap-6 p-4"><div><h1 className="text-2xl font-semibold tracking-tight">Sales</h1><p className="text-muted-foreground">Complete a sale and stock is deducted automatically.</p></div><Card><CardHeader><CardTitle>New sale</CardTitle><CardDescription>Select an in-stock product and quantity.</CardDescription></CardHeader><CardContent><form onSubmit={submit} className="grid gap-4 md:grid-cols-[1fr_12rem_auto]"><select aria-label="Product" className="border-input bg-background h-9 rounded-md border px-3 text-sm" value={item.product_id} onChange={(event) => form.setData('items', [{ ...item, product_id: event.target.value }])}><option value="">Select a product</option>{products.map((product) => <option key={product.id} value={product.id} disabled={product.stock === 0}>{product.name} — {formatCurrency(product.price)} ({product.stock} in stock)</option>)}</select><Input aria-label="Quantity" type="number" min="1" value={item.quantity} onChange={(event) => form.setData('items', [{ ...item, quantity: event.target.value }])} /><Button type="submit" disabled={form.processing || !item.product_id}>Complete sale</Button></form>{Object.keys(form.errors).length > 0 && <div className="mt-4"><AlertError errors={Object.values(form.errors)} /></div>}</CardContent></Card><Card><CardHeader><CardTitle>Recent sales</CardTitle><CardDescription>The latest 20 completed orders.</CardDescription></CardHeader><CardContent className="space-y-3">{sales.map((sale) => <div key={sale.id} className="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-medium">Order #{sale.id}</p><p className="text-muted-foreground text-sm">{sale.items.map((item) => `${item.product.name} × ${item.quantity}`).join(', ')}</p></div><div className="text-sm sm:text-right"><p className="font-semibold">{formatCurrency(sale.total)}</p><p className="text-muted-foreground">{new Intl.DateTimeFormat('en-BD', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(sale.created_at))}</p></div></div>)}{sales.length === 0 && <p className="py-8 text-center text-muted-foreground">No sales have been recorded yet.</p>}</CardContent></Card></div></>;
}
Sales.layout = { breadcrumbs: [{ title: 'Sales', href: index() }] };
