<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Splennet - Student UGC Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#ea580c',
                    },
                    fontFamily: {
                        urbanist: ['Urbanist', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Urbanist', sans-serif;
        }
        /* Accessibility & Contrast Improvements */
        input::placeholder, textarea::placeholder {
            color: #4b5563 !important; /* gray-600 */
        }
        .dark input::placeholder, .dark textarea::placeholder {
            color: #9ca3af !important; /* gray-400 */
        }
        
        /* Stronger borders for inputs by default */
        input, select, textarea {
            border-color: #d1d5db !important; /* gray-300 */
        }
        .dark input, .dark select, .dark textarea {
            border-color: #374151 !important; /* gray-700 */
        }

        /* Improved Focus States */
        input:focus, select:focus, textarea:focus {
            outline: 2px solid #4f46e5 !important;
            outline-offset: 1px;
            border-color: transparent !important;
        }

        /* Better contrast for faint text */
        .text-gray-400 { color: #64748b !important; } /* gray-400 -> gray-450-ish (slate-500) */
        .dark .text-gray-400 { color: #94a3b8 !important; } /* slate-400 */
        
        .text-gray-500 { color: #475569 !important; } /* gray-500 -> slate-600 */
        .dark .text-gray-500 { color: #cbd5e1 !important; } /* slate-300 */

        .text-gray-600 { color: #1e293b !important; } /* slate-800 */
        .dark .text-gray-600 { color: #f1f5f9 !important; } /* slate-100 */

        /* Card, Divider & Border visibility */
        .border-gray-100, .divide-gray-100 { border-color: #cbd5e1 !important; } /* slate-300 */
        .dark .border-gray-800, .dark .divide-gray-800 { border-color: #334155 !important; } /* slate-700 */
        .dark .border-gray-700, .dark .divide-gray-700 { border-color: #475569 !important; } /* slate-600 */

        /* Input Backgrounds - more distinct from page background */
        .bg-gray-50 { background-color: #f8fafc !important; } /* slate-50 */
        .dark .bg-gray-800 { background-color: #1e293b !important; } /* slate-800 */
        
        /* Interactive Element Polishing */
        .hover\:border-primary:hover, .hover\:border-secondary:hover {
            border-width: 2px !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }

        /* Forms accessibility */
        label {
            font-weight: 700 !important;
            color: #334155 !important; /* slate-700 */
            letter-spacing: 0.025em;
        }
        .dark label {
            color: #f8fafc !important; /* slate-50 */
        }

        /* Smoother transitions for interactive elements */
        input, select, textarea, button, a {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        /* Focus State Polish */
        input:focus, textarea:focus, select:focus {
            background-color: #ffffff !important;
            border-color: #4f46e5 !important;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1) !important;
            outline: none !important;
        }
        .dark input:focus, .dark textarea:focus, .dark select:focus {
            background-color: #0f172a !important;
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2) !important;
        }

        /* Empty state contrast */
        .italic.text-gray-500, .italic.text-gray-400 {
            color: #475569 !important;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-white dark:bg-gray-950">
    <header>
        <nav id="nav" class="absolute group z-10 w-full border-b border-black/5 dark:border-white/5 lg:border-transparent">
            <div class="max-w-7xl mx-auto px-6 md:px-12">
                <div class="relative flex flex-wrap items-center justify-between gap-6 py-3 md:gap-0 md:py-4">
                    <div class="relative z-20 flex w-full justify-between md:px-0 lg:w-fit">
                        <a href="<?php echo APP_URL; ?>index.php" aria-label="logo" class="flex items-center space-x-2">
                            <div aria-hidden="true" class="flex space-x-1">
                                <div class="w-4 h-4 rounded-full bg-gray-900 dark:bg-white"></div>
                                <div class="h-6 w-2 bg-primary"></div>
                            </div>
                            <span class="text-2xl font-bold text-gray-900 dark:text-white">Splennet</span>
                        </a>

                        <div class="relative flex max-h-10 items-center lg:hidden">
                            <button aria-label="humburger" id="hamburger" class="relative -mr-6 p-6 active:scale-95 duration-300">
                                <div aria-hidden="true" id="line" class="m-auto h-0.5 w-5 rounded bg-gray-950 transition duration-300 dark:bg-white origin-top group-data-[state=active]:rotate-45 group-data-[state=active]:translate-y-1.5"></div>
                                <div aria-hidden="true" id="line2" class="m-auto mt-2 h-0.5 w-5 rounded bg-gray-950 transition duration-300 dark:bg-white origin-bottom group-data-[state=active]:-rotate-45 group-data-[state=active]:-translate-y-1"></div>
                            </button>
                        </div>
                    </div>
                    <div id="navLayer" aria-hidden="true" class="fixed inset-0 z-10 h-screen w-screen origin-bottom scale-y-0 bg-white/70 backdrop-blur-2xl transition duration-500 group-data-[state=active]:origin-top group-data-[state=active]:scale-y-100 dark:bg-gray-950/70 lg:hidden"></div>
                    <div id="navlinks" class="invisible absolute top-full left-0 z-20 w-full origin-top-right translate-y-1 scale-90 flex-col flex-wrap justify-end gap-6 rounded-3xl border border-gray-100 bg-white p-8 opacity-0 shadow-2xl shadow-gray-600/10 transition-all duration-300 dark:border-gray-700 dark:bg-gray-800 dark:shadow-none lg:visible lg:relative lg:flex lg:w-fit lg:translate-y-0 lg:scale-100 lg:flex-row lg:items-center lg:gap-0 lg:border-none lg:bg-transparent lg:p-0 lg:opacity-100 lg:shadow-none lg:dark:bg-transparent group-data-[state=active]:visible group-data-[state=active]:scale-100 group-data-[state=active]:opacity-100 lg:group-data-[state=active]:translate-y-0">
                        <div class="w-full text-gray-600 dark:text-gray-200 lg:w-auto lg:pr-4 lg:pt-0">
                            <div id="links-group" class="flex flex-col gap-6 tracking-wide lg:flex-row lg:gap-0 lg:text-sm">
                                <a href="<?php echo APP_URL; ?>brands.php" class="hover:text-primary block transition dark:hover:text-white md:px-4">
                                    <span>For Brands</span>
                                </a>
                                <a href="<?php echo APP_URL; ?>creators.php" class="hover:text-primary block transition dark:hover:text-white md:px-4">
                                    <span>For Creators</span>
                                </a>
                                <a href="<?php echo APP_URL; ?>pricing.php" class="hover:text-primary block transition dark:hover:text-white md:px-4">
                                    <span>Pricing</span>
                                </a>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="<?php echo APP_URL . ($_SESSION['role'] === 'admin' ? 'admin' : $_SESSION['role']); ?>/dashboard.php" class="hover:text-primary block transition dark:hover:text-white md:px-4 relative flex items-center">
                                        <span>Dashboard</span>
                                        <span id="global-unread-badge" class="ml-1 w-4 h-4 bg-secondary text-white text-[9px] font-black rounded-full flex items-center justify-center" style="display:none;">0</span>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo APP_URL; ?>login.php" class="hover:text-primary block transition dark:hover:text-white md:px-4">
                                        <span>Login</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-12 lg:mt-0">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="<?php echo APP_URL; ?>logout.php" class="relative flex h-9 w-full items-center justify-center px-4 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 sm:w-max">
                                    <span class="relative text-sm font-semibold text-white">Logout</span>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo APP_URL; ?>register.php" class="relative flex h-9 w-full items-center justify-center px-4 before:absolute before:inset-0 before:rounded-full before:bg-primary before:transition before:duration-300 hover:before:scale-105 active:duration-75 active:before:scale-95 sm:w-max">
                                    <span class="relative text-sm font-semibold text-white">Join Now</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <script>
        let isToggled = false;
        const navbar = document.querySelector("#nav");
        const menuBtn = document.querySelector("#hamburger");
        const linksGroup = navbar.querySelector("#links-group");
        const links = Array.from(linksGroup.children);

        function toggleNavlinks() {
            if (isToggled) {
                navbar.setAttribute("data-state", "active");
            } else {
                navbar.setAttribute("data-state", "");
            }
        }

        menuBtn.addEventListener("click", () => {
            isToggled = !isToggled;
            toggleNavlinks();
        });

        links.forEach((link) => {
            link.addEventListener("click", () => {
                isToggled = false;
                toggleNavlinks();
            });
        });
    </script>
    <script>window.APP_URL = <?php echo json_encode(APP_URL); ?>;</script>
