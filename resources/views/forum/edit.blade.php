@extends('layouts.app')

@section('title', __('forum_ui.edit_post').' · '.__('ui.app_name'))

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-6 text-xl font-semibold">{{ __('forum_ui.edit_post') }}</h1>

        @include('forum._form', [
            'action' => route('forum.update', $post),
            'method' => 'PUT',
            'categories' => $categories,
            'post' => $post,
        ])
    </div>
@endsection
