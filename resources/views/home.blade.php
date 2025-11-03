@extends('layouts.app')

@section('title', 'Home Page')

@section('content')
    <div style="text-align: center; padding: 3rem 0;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem; color: #2c3e50;">
            Welcome to Your PHP Framework
        </h1>
        <p style="font-size: 1.2rem; color: #7f8c8d; margin-bottom: 2rem;">
            A Laravel-inspired framework built with native PHP
        </p>
        
        @component('components.card')
            @slot('title')
                Getting Started
            @endslot
            
            <p>This is an example of using layouts, sections, and components in your views.</p>
            <ul style="list-style: none; padding: 1rem 0;">
                <li>✓ Blade-like templating</li>
                <li>✓ Eloquent-like ORM</li>
                <li>✓ RESTful routing</li>
                <li>✓ Middleware support</li>
                <li>✓ Migration system</li>
                <li>✓ Validation system</li>
            </ul>
            
            @slot('footer')
                <a href="/users" style="color: #3498db; text-decoration: none;">View Users →</a>
            @endslot
        @endcomponent
        
        @component('components.card')
            @slot('title')
                Framework Features
            @endslot
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <h4>Collections</h4>
                    <p style="font-size: 0.9rem; color: #7f8c8d;">Powerful array manipulation</p>
                </div>
                <div>
                    <h4>Service Providers</h4>
                    <p style="font-size: 0.9rem; color: #7f8c8d;">SDK integration support</p>
                </div>
                <div>
                    <h4>Artisan CLI</h4>
                    <p style="font-size: 0.9rem; color: #7f8c8d;">25+ commands available</p>
                </div>
            </div>
        @endcomponent
    </div>
@endsection

@section('scripts')
    <script>
        console.log('Framework loaded successfully!');
    </script>
@endsection
