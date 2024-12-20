<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ env("APP_NAME") }}</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
</head>
<body class="bg-white text-slate-900">
<header class="bg-white relative w-full z-10">
    <div class="mx-auto max-w-screen-xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <div class="md:flex md:items-center md:gap-12">
                <a class="block text-teal-600 text-3xl" href="{{ route('home') }}">
                    E-Shop
                </a>
            </div>
            <nav aria-label="Global" class="py-2">
                <ul class="flex items-center gap-6 text-sm">
                    <li>
                        <div id="hs-navbar-basic-usage" class="hidden hs-collapse overflow-hidden transition-all duration-300 basis-full grow sm:block" aria-labelledby="hs-navbar-basic-usage-collapse">
                            <div class="hs-dropdown [--strategy:static] sm:[--strategy:absolute] [--adaptive:none] sm:[--trigger:hover] [--auto-close:false] [--is-collapse:true] sm:[--is-collapse:false] ">
                                <button id="hs-mega-menu" type="button" class="hs-dropdown-toggle sm:p-2 flex items-center w-full text-gray-500 hover:text-gray-500/75 focus:outline-none" aria-haspopup="menu" aria-expanded="false" aria-label="Mega Menu">
                                    Categories
                                </button>
                                <div class="hs-dropdown-menu transition-[height] sm:transition-[opacity,margin] duration-300 ease-in-out sm:duration-[150ms] hs-dropdown-open:opacity-100 opacity-0 w-full hidden z-20 sm:mt-3 top-full min-w-72 start-0 bg-white sm:shadow-md rounded-lg sm:px-2 before:absolute" role="menu" aria-orientation="vertical" aria-labelledby="hs-mega-menu">
                                    <form action="{{ route('showProducts') }}" method="post">
                                        @csrf
                                        <div class="sm:grid grid-cols-3">
                                            <div class="flex flex-col mb-2">
                                                <button class="py-2 px-3 rounded-lg text-teal-600 hover:bg-teal-600 hover:text-slate-50" name="category" value="1" onclick="this.form.submit()">
                                                    Shirts
                                                </button>
                                                <button class="py-2 px-3 rounded-lg text-teal-600 hover:bg-teal-600 hover:text-slate-50" name="category" value="2" onclick="this.form.submit()">
                                                    Pants
                                                </button>
                                                <button class="py-2 px-3 rounded-lg text-teal-600 hover:bg-teal-600 hover:text-slate-50" name="category" value="3" onclick="this.form.submit()">
                                                    Jackets
                                                </button>
                                            </div>

                                            <div class="flex flex-col mb-2">
                                                <button class="py-2 px-3 rounded-lg text-teal-600 hover:bg-teal-600 hover:text-slate-50" name="category" value="4" onclick="this.form.submit()">
                                                    Shoes
                                                </button>
                                                <button class="py-2 px-3 rounded-lg text-teal-600 hover:bg-teal-600 hover:text-slate-50" name="category" value="5" onclick="this.form.submit()">
                                                    Underwear
                                                </button>
                                                <button class="px-3 py-2 rounded-lg text-teal-600 hover:bg-teal-600 hover:text-slate-50" name="category" value="6" onclick="this.form.submit()">
                                                    Accessories
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li>
                        <a class="text-gray-500 transition hover:text-gray-500/75" href="{{ route('products') }}"> Products </a>
                    </li>

                    <li>
                        <a class="text-gray-500 transition hover:text-gray-500/75" href="{{ route('about') }}"> About </a>
                    </li>

                    <li>
                        <a class="text-gray-500 transition hover:text-gray-500/75" href="{{ route('contact') }}"> Contact </a>
                    </li>
                </ul>
            </nav>

            @guest

                <div class="flex items-center gap-4">
                    <div class="sm:flex sm:gap-4">
                        <a
                            class="rounded-md bg-teal-600 px-5 py-2.5 text-sm font-medium text-white shadow"
                            href="{{ route('login') }}"
                        >
                            Login
                        </a>

                        <div class="hidden sm:flex">
                            <a
                                class="rounded-md bg-gray-100 px-5 py-2.5 text-sm font-medium text-teal-600"
                                href="{{ route('register') }}"
                            >
                                Register
                            </a>
                        </div>
                    </div>
                </div>
            @endguest
            @auth
                <div class="flex justify-between items-center">
                    <a class="relative pb-2 mx-10" href="{{ route('cart') }}">
                        <div class="t-0 absolute left-3">
                            <p class="flex h-2 w-2 items-center justify-center rounded-full bg-teal-500 p-3 text-xs text-white">{{ count(session('cart', [])); }}</p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="file: mt-4 h-6 w-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                    </a>
                    <div>
                        <button id="dropdownDividerButton" data-dropdown-toggle="dropdownDivider" class="font-medium rounded-lg text-sm py-2.5 text-center inline-flex items-center" type="button">
                            <svg class="h-8 w-8 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </button>

                        <div id="dropdownDivider" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow w-36 dark:bg-gray-700 dark:divide-gray-600">
                            <ul class="py-2 text-base text-gray-700 dark:text-gray-200" aria-labelledby="dropdownDividerButton">
                              <li>
                                <a href="{{ route('dashboard') }}" class="block text-center px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">My Orders</a>
                              </li>
                              <li>
                                <a href="{{ route('profile') }}" class="block text-center px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Profile</a>
                              </li>
                              <li>
                                @role('admin')
                                <a href="{{ route('AdminPanel') }}" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Admin Panel</a>
                                @endrole
                              </li>
                            </ul>
                            <div class="py-2">
                                <form action="{{ route('logout') }}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button class="block w-full text-sm px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endauth
        </div>
    </div>
</header>


<main>
    {{ $slot }}
</main>

<footer class="bg-gray-100">
    <div class="mx-auto max-w-screen-xl space-y-8 px-4 py-16 sm:px-6 lg:space-y-16 lg:px-8">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div>
                <div class="text-teal-600 text-2xl">
                    <p>E-Shop</p>
                </div>

                <p class="mt-4 max-w-xs text-gray-500">
                    Lorem ipsum dolor, sit amet consectetur adipisicing elit. Esse non cupiditate quae nam
                    molestias.
                </p>

                <ul class="mt-8 flex gap-6">
                    <li>
                        <a
                            href="#"
                            rel="noreferrer"
                            target="_blank"
                            class="text-gray-700 transition hover:opacity-75"
                        >
                            <span class="sr-only">Facebook</span>

                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </a>
                    </li>

                    <li>
                        <a
                            href="#"
                            rel="noreferrer"
                            target="_blank"
                            class="text-gray-700 transition hover:opacity-75"
                        >
                            <span class="sr-only">Instagram</span>

                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </a>
                    </li>

                    <li>
                        <a
                            href="#"
                            rel="noreferrer"
                            target="_blank"
                            class="text-gray-700 transition hover:opacity-75"
                        >
                            <span class="sr-only">Twitter</span>

                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"
                                />
                            </svg>
                        </a>
                    </li>

                    <li>
                        <a
                            href="#"
                            rel="noreferrer"
                            target="_blank"
                            class="text-gray-700 transition hover:opacity-75"
                        >
                            <span class="sr-only">GitHub</span>

                            <svg class="size-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </a>
                    </li>

                </ul>
            </div>

            <div class="flex align-items-center justify-between">
                {{-- <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:col-span-2 lg:grid-cols-4"> --}}
                <div>
                    <p class="font-medium text-gray-900">Categories</p>

                    <ul class="mt-6 space-y-4 text-sm">
                        <li>
                            <a href="{{ route('products') }}" class="text-gray-700 transition hover:opacity-75"> Shirts </a>
                        </li>

                        <li>
                            <a href="{{ route('products') }}" class="text-gray-700 transition hover:opacity-75"> Pants </a>
                        </li>

                        <li>
                            <a href="{{ route('products') }}" class="text-gray-700 transition hover:opacity-75"> Jackets </a>
                        </li>

                        <li>
                            <a href="{{ route('products') }}" class="text-gray-700 transition hover:opacity-75"> Shoes </a>
                        </li>

                        <li>
                            <a href="{{ route('products') }}" class="text-gray-700 transition hover:opacity-75"> Accessories </a>
                        </li>
                    </ul>
                </div>


                <div>
                    <p class="font-medium text-gray-900">Helpful Links</p>

                    <ul class="mt-6 space-y-4 text-sm">
                        <li>
                            <a href="{{ route('contact') }}" class="text-gray-700 transition hover:opacity-75"> Contact </a>
                        </li>

                        <li>
                            <a href="{{ route('contact') }}" class="text-gray-700 transition hover:opacity-75"> FAQs </a>
                        </li>

                        <li>
                            <a href="{{ route('about') }}" class="text-gray-700 transition hover:opacity-75"> About </a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

        <p class="text-xs text-gray-500">&copy; 2022. E-Shop. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
