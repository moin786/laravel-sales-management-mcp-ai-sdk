import { Head } from '@inertiajs/react';
import { BotMessageSquare, Plus, SendHorizontal, UserRound } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index, store } from '@/routes/ai-chat';

type Message = { role: 'assistant' | 'user'; content: string };
type ChatResponse = { message: string; conversation_id?: string };

export default function AiChat() {
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [isSending, setIsSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const question = input.trim();
        if (question === '' || isSending) return;

        setMessages((currentMessages) => [...currentMessages, { role: 'user', content: question }]);
        setInput('');
        setError(null);
        setIsSending(true);

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch(store.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}) },
                body: JSON.stringify({ message: question, conversation_id: conversationId }),
            });
            const payload = await response.json() as ChatResponse;

            if (!response.ok) throw new Error(payload.message);

            if (payload.conversation_id) setConversationId(payload.conversation_id);
            setMessages((currentMessages) => [...currentMessages, { role: 'assistant', content: payload.message }]);
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Unable to contact the sales assistant.');
        } finally {
            setIsSending(false);
        }
    }

    function startNewConversation() {
        setMessages([]);
        setConversationId(null);
        setInput('');
        setError(null);
    }

    return <><Head title="AI Sales Assistant" /><div className="flex flex-1 flex-col gap-6 p-4"><div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h1 className="text-2xl font-semibold tracking-tight">AI Sales Assistant</h1><p className="text-muted-foreground">Ask about current products, inventory, and sales performance.</p></div><Button variant="outline" onClick={startNewConversation}><Plus />New chat</Button></div><Card className="min-h-[calc(100vh-15rem)]"><CardHeader><CardTitle className="flex items-center gap-2"><BotMessageSquare className="size-5" />Sales intelligence</CardTitle><CardDescription>Answers are grounded in your live sales MCP data.</CardDescription></CardHeader><CardContent className="flex min-h-[28rem] flex-1 flex-col gap-4"><div className="flex flex-1 flex-col gap-4 overflow-y-auto rounded-lg border bg-muted/30 p-4">{messages.length === 0 && <div className="m-auto max-w-md text-center"><BotMessageSquare className="text-muted-foreground mx-auto mb-3 size-8" /><p className="font-medium">How can I help with your sales?</p><p className="text-muted-foreground mt-1 text-sm">Try “Which products need restocking?” or “What was this month’s revenue?”</p></div>}{messages.map((message, messageIndex) => <div key={`${message.role}-${messageIndex}`} className={message.role === 'user' ? 'flex justify-end' : 'flex justify-start'}><div className={message.role === 'user' ? 'bg-primary text-primary-foreground max-w-[85%] rounded-xl px-4 py-3 text-sm' : 'bg-background max-w-[85%] rounded-xl border px-4 py-3 text-sm shadow-sm'}><div className="mb-1 flex items-center gap-2 text-xs opacity-70">{message.role === 'user' ? <UserRound className="size-3" /> : <BotMessageSquare className="size-3" />}{message.role === 'user' ? 'You' : 'Assistant'}</div><p className="whitespace-pre-wrap">{message.content}</p></div></div>)}{isSending && <div className="text-muted-foreground flex items-center gap-2 text-sm"><BotMessageSquare className="size-4 animate-pulse" />Analyzing your sales data…</div>}</div>{error && <p role="alert" className="text-destructive text-sm">{error}</p>}<form onSubmit={submit} className="flex gap-3"><Input aria-label="Sales question" value={input} onChange={(event) => setInput(event.target.value)} disabled={isSending} maxLength={4000} placeholder="Ask about your products, inventory, or sales…" /><Button type="submit" disabled={isSending || input.trim() === ''}><SendHorizontal />Send</Button></form></CardContent></Card></div></>;
}

AiChat.layout = { breadcrumbs: [{ title: 'AI Sales Assistant', href: index() }] };
