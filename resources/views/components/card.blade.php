<div class="{{ $type ?? 'info' }}-card" style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin: 1rem 0;">
    @isset($title)
        <h3 style="margin-bottom: 1rem; color: #2c3e50;">{{ $title }}</h3>
    @endisset
    
    <div class="card-body">
        {{ $slot }}
    </div>
    
    @isset($footer)
        <div class="card-footer" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
            {{ $footer }}
        </div>
    @endisset
</div>
