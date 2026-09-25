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
            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                @unless ($post->is_locked)
                    <div x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="muted hover:text-frost-300">{{ __('forum_ui.reply') }}</button>

                        <form x-show="open" x-cloak method="POST" action="{{ route('forum.comments.store', $post) }}" class="mt-2 space-y-2">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->getKey() }}">
                            <textarea name="content" rows="2" class="input" placeholder="{{ __('forum_ui.reply_placeholder') }}" required></textarea>
                            <button type="submit" class="btn-ghost">{{ __('forum_ui.submit_reply') }}</button>
                        </form>
                    </div>
                @endunless

                {{-- Moderator actions: hiding keeps the row for the audit trail,
                     deleting removes it from the thread entirely. --}}
                @can('hide', $comment)
                    @if ($comment->status === \App\Models\Comment::STATUS_HIDDEN)
                        <form method="POST" action="{{ route('moderation.comments.unhide', $comment) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="muted hover:text-frost-300">{{ __('moderation_ui.comment.unhide') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('moderation.comments.hide', $comment) }}">
                            @csrf
                            <button type="submit" class="muted hover:text-frost-300">{{ __('moderation_ui.comment.hide') }}</button>
                        </form>
                    @endif
                @endcan

                @can('delete', $comment)
                    <form method="POST" action="{{ route('moderation.comments.destroy', $comment) }}"
                          onsubmit="return confirm('{{ __('ui.action.confirm_delete') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-400 hover:text-red-300">{{ __('moderation_ui.comment.delete') }}</button>
                    </form>
                @endcan

                @if ($comment->author_id !== auth()->id())
                    @include('forum._report', [
                        'action' => route('forum.comments.report', [$post, $comment]),
                        'alreadyReported' => in_array($comment->id, $reportedComments ?? [], true),
                        'scope' => 'comment-'.$comment->getKey(),
                    ])
                @endif
            </div>
        @endauth
    </div>

    @if ($depth < $maxDepth && isset($byParent[$comment->getKey()]))
        @foreach ($byParent[$comment->getKey()] as $child)
            @include('forum._comment', [
                'comment' => $child,
                'byParent' => $byParent,
                'depth' => $depth + 1,
                'maxDepth' => $maxDepth,
                'post' => $post,
                'reportedComments' => $reportedComments ?? [],
            ])
        @endforeach
    @endif
</div>
