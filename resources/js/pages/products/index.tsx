import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index, store } from '@/routes/products';

type Product = { id: number; name: string; sku: string; price: string; stock: number };
const formatCurrency = (amount: string) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT' }).format(Number(amount));

export default function Products({ products }: { products: Product[] }) {
    const form = useForm({ name: '', sku: '', price: '', stock: '' });
    function submit(event: FormEvent<HTMLFormElement>) { event.preventDefault(); form.submit(store(), { onSuccess: () => form.reset() }); }
    return <><Head title="Products" /><div className="flex flex-1 flex-col gap-6 p-4"><div><h1 className="text-2xl font-semibold tracking-tight">Products</h1><p className="text-muted-foreground">Maintain prices, SKUs, and available inventory.</p></div><Card><CardHeader><CardTitle>Add product</CardTitle><CardDescription>New items are immediately available to the sales desk.</CardDescription></CardHeader><CardContent><form onSubmit={submit} className="grid gap-4 md:grid-cols-2 xl:grid-cols-5"><Input aria-label="Product name" placeholder="Product name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /><Input aria-label="SKU" placeholder="SKU" value={form.data.sku} onChange={(event) => form.setData('sku', event.target.value)} /><Input aria-label="Price" type="number" min="0" step="0.01" placeholder="Price (BDT)" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} /><Input aria-label="Stock" type="number" min="0" placeholder="Opening stock" value={form.data.stock} onChange={(event) => form.setData('stock', event.target.value)} /><Button type="submit" disabled={form.processing}>Add product</Button></form>{Object.keys(form.errors).length > 0 && <div className="mt-4"><AlertError errors={Object.values(form.errors)} /></div>}</CardContent></Card><Card><CardHeader><CardTitle>Inventory</CardTitle><CardDescription>{products.length} product{products.length === 1 ? '' : 's'} in the catalog.</CardDescription></CardHeader><CardContent className="overflow-x-auto"><table className="w-full min-w-[600px] text-left text-sm"><thead className="border-b text-muted-foreground"><tr><th className="pb-3 font-medium">Product</th><th className="pb-3 font-medium">SKU</th><th className="pb-3 font-medium">Price</th><th className="pb-3 font-medium">Stock</th></tr></thead><tbody>{products.map((product) => <tr key={product.id} className="border-b last:border-0"><td className="py-4 font-medium">{product.name}</td><td className="py-4 text-muted-foreground">{product.sku}</td><td className="py-4">{formatCurrency(product.price)}</td><td className="py-4"><span className={product.stock < 10 ? 'font-medium text-amber-600 dark:text-amber-400' : ''}>{product.stock}</span></td></tr>)}</tbody></table>{products.length === 0 && <p className="py-8 text-center text-muted-foreground">Add your first product to begin recording sales.</p>}</CardContent></Card></div></>;
}
Products.layout = { breadcrumbs: [{ title: 'Products', href: index() }] };
