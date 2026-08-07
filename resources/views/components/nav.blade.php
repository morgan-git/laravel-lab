<!-- NAVBAR -->
<div class="navbar shadow-sm relative z-50">
    <div class="navbar-start">

        <!-- MOBILE DROPDOWN -->
        <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-ghost lg:hidden">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-5 w-5"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M4 6h16M4 12h8m-8 6h16"/>
                </svg>
            </div>

            <ul tabindex="-1"
                class="menu menu-sm dropdown-content rounded-box z-100 mt-3 w-52 p-2 shadow bg-base-100">
                <li><a href="/">Home</a></li>

                <li>
                    <a href="/ideas">Ideas</a>
                    <ul class="p-2 z-500">
                        <li><a href="/ideas/create">Create</a></li>
                        <li><a href="/ideas/">List</a></li>
                    </ul>
                </li>
                @can('view-admin')
                    <li>
                        <a href="/admin">Admin</a>
                        <ul class="p-2 z-500">
                            <li><a href="/admin/feed-sources">Feed Manager</a></li>
                            <li><a href="/admin/jobs/">Job Queue</a></li>
                        </ul>
                    </li>
                @endcan
                <li><a href="/about">About</a></li>
                <li><a href="/contact">Contact</a></li>
            </ul>
        </div>

     <a href="/" alt="AFTERtheSYNTAX"><img src="/images/after_the_syntax_logo.png" width="200" /></a>
    </div>

    <!-- DESKTOP MENU -->
    <div class="navbar-center hidden lg:flex">
        <ul class="menu menu-horizontal px-1">
            <li><a href="/">Home</a></li>

            <li>
                <details>
                    <summary>Ideas</summary>
                    <ul class="p-2 w-40 rounded-box shadow z-100">
                        <li><a href="/ideas">List</a></li>
                        <li><a href="/ideas/create">Create</a></li>
                    </ul>
                </details>
            </li>
            @can('view-admin')
              <li>
                <details>
                    <summary>Admin</summary>
                    <ul class="p-2 w-40 rounded-box shadow z-100">
                        <li><a href="/admin/feed-sources">Feed Manager</a></li>
                        <li><a href="/admin/jobs/">Job Queue</a></li>
                        <li><a href="/admin/users/">Users</a></li>
                    </ul>
                </details>
            </li>
            @endcan
            <li><a href="/about">About</a></li>
            <li><a href="/contact">Contact</a></li>
        </ul>
    </div>

    <div class="navbar-end space-x-2">

        @auth
            <form method="POST" action="/logout">
            @csrf
            @method('DELETE')
            <button class="btn btn-ghost" data-test="logout-button">Log Out</button>
            </form>
        @else
                    <a class="btn btn-login" href="/login">Log In</a>
                    <a class="btn btn-primary" href="/register">Register</a>

        @endauth
    </div>
</div>
