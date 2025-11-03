<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Welcome - VTPHP Framework</title>
    <?php echo vite('resources/js/app.js'); ?>
</head>

<body
    class="bg-gradient-to-br from-blue-600 via-purple-600 to-pink-500 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-4xl w-full">
        <div class="bg-white rounded-3xl shadow-2xl p-12 text-center" x-data="{ show: false }"
            x-init="setTimeout(() => show = true, 100)">
            <!-- Logo/Title -->
            <div class="mb-8" x-show="show" x-transition.duration.500ms>
                <h1 class="text-6xl font-bold mb-4">
                    <span class="text-gradient">VTPHP</span>
                </h1>
                <p class="text-2xl text-gray-600 font-light">Virtual Tech PHP Framework</p>
            </div>

            <!-- Tagline -->
            <p class="text-lg text-gray-700 mb-12 max-w-2xl mx-auto" x-show="show" x-transition.delay.100ms>
                A modern, Laravel-inspired PHP framework with Eloquent ORM, Blade templating,
                and powered by Vite + Tailwind CSS + Alpine.js
            </p>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12" x-show="show"
                x-transition.delay.200ms>
                <div class="card hover:shadow-xl transition-shadow duration-300">
                    <div class="text-4xl mb-3">⚡</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Fast Routing</h3>
                    <p class="text-gray-600 text-sm">RESTful routing with middleware, groups, and resource controllers
                    </p>
                </div>

                <div class="card hover:shadow-xl transition-shadow duration-300">
                    <div class="text-4xl mb-3">🗄️</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Eloquent ORM</h3>
                    <p class="text-gray-600 text-sm">Beautiful ActiveRecord implementation with relationships</p>
                </div>

                <div class="card hover:shadow-xl transition-shadow duration-300">
                    <div class="text-4xl mb-3">🎨</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Blade Templates</h3>
                    <p class="text-gray-600 text-sm">.blade.php files with layouts, components, and directives</p>
                </div>

                <div class="card hover:shadow-xl transition-shadow duration-300">
                    <div class="text-4xl mb-3">🎯</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Tailwind CSS</h3>
                    <p class="text-gray-600 text-sm">Utility-first CSS with Vite for instant HMR</p>
                </div>

                <div class="card hover:shadow-xl transition-shadow duration-300">
                    <div class="text-4xl mb-3">🔒</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Security</h3>
                    <p class="text-gray-600 text-sm">CSRF protection, validation, auth middleware</p>
                </div>

                <div class="card hover:shadow-xl transition-shadow duration-300">
                    <div class="text-4xl mb-3">🛠️</div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">95+ Commands</h3>
                    <p class="text-gray-600 text-sm">Powerful Artisan CLI for rapid development</p>
                </div>
            </div>

            <!-- CTA Buttons -->
            <div class="flex flex-wrap gap-4 justify-center mb-8" x-show="show" x-transition.delay.300ms>
                <a href="/docs" class="btn-primary">
                    📚 Documentation
                </a>
                <a href="/users" class="btn-secondary">
                    🚀 View Demo
                </a>
                <a href="https://github.com" target="_blank"
                    class="bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                    ⭐ Star on GitHub
                </a>
            </div>

            <!-- Version Info -->
            <div class="text-gray-500 text-sm" x-show="show" x-transition.delay.400ms>
                <p class="mb-2">VTPHP Framework v1.0.0</p>
                <p>PHP {{ phpversion() }} | Vite + Tailwind CSS + Alpine.js</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-3 gap-4 mt-8 text-white text-center" x-show="show" x-transition.delay.500ms>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                <div class="text-3xl font-bold">95+</div>
                <div class="text-sm">Artisan Commands</div>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                <div class="text-3xl font-bold">40+</div>
                <div class="text-sm">Collection Methods</div>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                <div class="text-3xl font-bold">20+</div>
                <div class="text-sm">Validation Rules</div>
            </div>
        </div>
    </div>
</body>

</html>