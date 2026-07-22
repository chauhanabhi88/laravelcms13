<div class="card card-primary card-outline">
    @foreach($moduleList as $module)
    @php
        $path = $module->getPath();
    @endphp
        @if(file_exists($path.'/config/settings.php'))
            <button data-module="{{ $module->getLowerName() }}" data-url="{{ route('admin.settings.getModuleSetting', updateUrlParams()) }}" class="btn btn-primary module"> {{ $module->getName()   }}</button>
        @endif
    @endforeach
</div>
