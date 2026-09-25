{{--
    The reporting control. $action is the form target, $alreadyReported tells the
    button whether this member has an open report on the object already, and
    $scope keeps the Alpine state unique when several forms share a page.
--}}
@php($scope = $scope ?? 'report')

<div x-data="{ open: false }" class="relative">
    @if ($alreadyReported)
        <span class="btn-ghost cursor-default opacity-60">{{ __('moderation_ui.report.already') }}</span>
    @else
        <button type="button" @click="open = !open" class="btn-ghost">{{ __('moderation_ui.report.action') }}</button>

        <form x-show="open" x-cloak method="POST" action="{{ $action }}"
              class="absolute right-0 z-20 mt-2 w-72 space-y-2 rounded-md border border-ink-800 bg-ink-900 p-3 text-sm shadow-xl">
            @csrf

            <p class="font-medium text-ink-100">{{ __('moderation_ui.report.title') }}</p>

            <div>
                <label for="reason-{{ $scope }}" class="label">{{ __('moderation_ui.report.reason') }}</label>
                <select id="reason-{{ $scope }}" name="reason" class="input">
                    @foreach ($reportReasons as $reason)
                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="detail-{{ $scope }}" class="label">{{ __('moderation_ui.report.detail') }}</label>
                <textarea id="detail-{{ $scope }}" name="detail" rows="3" maxlength="1000" class="input"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn-primary">{{ __('moderation_ui.report.submit') }}</button>
                <button type="button" @click="open = false" class="btn-ghost">{{ __('moderation_ui.report.cancel') }}</button>
            </div>
        </form>
    @endif
</div>
