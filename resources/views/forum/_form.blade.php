@php
    $post = $post ?? null;
    $selectedCategory = old('category_id', $post?->category_id);
    $selectedStatus = old('status', $post?->status ?? \App\Models\Post::STATUS_PUBLISHED);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label class="label" for="title">{{ __('forum_ui.post_title') }}</label>
        <input id="title" name="title" type="text" value="{{ old('title', $post?->title) }}" class="input" required maxlength="200">
        @error('title')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="category_id">{{ __('forum_ui.filter_category') }}</label>
        <select id="category_id" name="category_id" class="input w-full sm:w-60">
            <option value="">{{ __('ui.all_categories') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->getKey() }}" @selected((string) $selectedCategory === (string) $category->getKey())>{{ $category->displayName() }}</option>
            @endforeach
        </select>
        @error('category_id')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="content">{{ __('forum_ui.post_content') }}</label>
        <textarea id="content" name="content" rows="14" class="input font-mono text-[13px]" required>{{ old('content', $post?->content) }}</textarea>
        <p class="mt-1 text-xs muted">{{ __('forum_ui.markdown_hint') }}</p>
        @error('content')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="submit" name="status" value="{{ \App\Models\Post::STATUS_PUBLISHED }}" class="btn-primary">{{ __('forum_ui.publish') }}</button>
        <button type="submit" name="status" value="{{ \App\Models\Post::STATUS_DRAFT }}" class="btn-ghost">{{ __('forum_ui.save_draft') }}</button>
        <a href="{{ $post ? route('forum.show', $post) : route('forum.index') }}" class="text-sm muted">{{ __('ui.action.cancel') }}</a>
    </div>

    @if ($selectedStatus === \App\Models\Post::STATUS_DRAFT)
        <p class="text-xs muted">{{ __('ui.status.draft') }}</p>
    @endif
</form>
