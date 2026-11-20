<form method="POST" action="{{ route('admin.users.roles', $person) }}" class="row" style="gap:8px;align-items:flex-end" novalidate>
    @csrf @method('PUT')
    <fieldset style="border:0;padding:0;margin:0" class="row">
        <legend class="sr-only">Roles for {{ $person->name }}</legend>
        @foreach ($roles as $role)
            <label class="row small" style="gap:4px"><input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked($person->roles?->contains('id', $role->id))> {{ $role->label }}</label>
        @endforeach
    </fieldset>
    <label class="sr-only" for="reason-{{ $person->id }}">Reason</label>
    <input class="input" id="reason-{{ $person->id }}" name="reason" placeholder="Reason (required)" required minlength="5" style="min-height:34px;width:180px">
    <button class="btn btn-outline btn-sm" type="submit">Save</button>
</form>
