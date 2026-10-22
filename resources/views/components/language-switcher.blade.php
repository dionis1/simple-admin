<form method="POST" action="{{ route('locale.update') }}" class="flex gap-3 text-sm" aria-label="{{ __('Language') }}">
    @csrf
    <button type="submit" name="locale" value="en" lang="en" class="rounded px-2 py-1 focus:outline-2 focus:outline-offset-2" @if(app()->getLocale()==='en') aria-current="true" @endif>English</button>
    <button type="submit" name="locale" value="de" lang="de" class="rounded px-2 py-1 focus:outline-2 focus:outline-offset-2" @if(app()->getLocale()==='de') aria-current="true" @endif>Deutsch</button>
</form>
