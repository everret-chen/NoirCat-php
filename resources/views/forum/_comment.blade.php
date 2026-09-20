<div class="mt-3" style="margin-left: {{ ($depth - 1) * 20 }}px">
    <div class="rounded-lg border border-ink-800 bg-ink-900/60 p-3">
        <div class="flex items-center gap-2 text-xs muted">
            <span class="text-ink-200">{{ $comment->author?->username }}</span>
            <span>{{ optional($comment->created_at)->diffForHumans() }}</span>
            @if ($comment->status === \App\Models\Comment::STATUS_HIDDEN)
                <span class="badge bg-ink-700">{{ __('forum_ui.hidden') }}</span>
            @endif
        </div>

        {{-- Comment bodies are plain text: escaped, never rendered as HTML. --}}
        <p class="mt-2 whitespace-pre-line text-sm">{{ $comment->content }}</p>

        @auth
            <div x-data="{ open: false }" class="mt-2">
                <button type="button" @click="open = !open" class="text-xs muted hover:text-frost-300">{{ __('forum_ui.reply') }}</button>

                <form x-show="open" x-cloak method="POST" action="{{ route('forum.comments.store', $comment->post_id) }}" class="mt-2 space-y-2">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $comment->getKey() }}">
                    <textarea name="content" rows="2" class="input" placeholder="{{ __('forum_ui.reply_placeholder') }}" required></textarea>
                    <button type="submit" class="btn-ghost">{{ __('forum_ui.submit_reply') }}</button>
                </form>
            </div>
        @endauth
    </div>

    @if ($depth < $maxDepth && isset($byParent[$comment->getKey()]))
        @foreach ($byParent[$comment->getKey()] as $child)
            @include('forum._comment', ['comment' => $child, 'byParent' => $byParent, 'depth' => $depth + 1, 'maxDepth' => $maxDepth])
        @endforeach
    @endif
</div>
