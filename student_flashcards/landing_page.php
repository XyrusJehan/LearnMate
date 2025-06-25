<?php
// landing_page.php
session_start();
require '../db.php';
require '../includes/theme.php';

// Get user's theme preference if logged in
$theme = isset($_SESSION['user_id']) ? getCurrentTheme() : 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnmate - Smart Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <style>
        .gradient-text {
            background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 50%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        [data-theme="dark"] {
            --text-dark: #FFFFFF;
            --text-medium: #E0E0E0;
            --text-light: #CCCCCC;
            --bg-light: #1A1A1A;
            --bg-white: #2A2A2A;
            --border-light: #333333;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>" class="font-sans bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm py-4">
        <div class="max-w-6xl mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-white text-sm"></i>
                </div>
                <span class="ml-2 text-xl font-bold">Learn<span class="gradient-text">mate</span></span>
            </div>
            
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/LearnMate_1_pangalawa/index.php" class="text-gray-600 hover:text-violet-600">Dashboard</a>
                    <a href="/LearnMate_1_pangalawa/logout.php" class="text-gray-600 hover:text-violet-600">Logout</a>
                <?php else: ?>
                    <a href="/LearnMate_1_pangalawa/index.php" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-md">
                        Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <div class="md:flex items-center">
            <div class="md:w-1/2 mb-12 md:mb-0">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
                    Learn smarter, <span class="gradient-text">not harder</span>
                </h1>
                <p class="text-lg text-gray-600 mb-8">
                    Upload your study materials and get personalized quizzes, summaries, and progress tracking to help you master any subject.
                </p>
                <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <a href="/LearnMate_1_pangalawa/createAcc.php" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-3 rounded-md">
                        Get Started
                    </a>
                    <div class="flex items-center">
                        <span class="text-gray-600">Already have an account? </span>
                        <a href="/LearnMate_1_pangalawa/index.php" class="text-violet-600 hover:text-violet-700 font-medium ml-1">
                            Sign In
                        </a>
                    </div>
                </div>
            </div>
            <div class="md:w-1/2">
                <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1351&q=80" 
                     alt="Students learning" 
                     class="rounded-lg shadow-lg">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="bg-white py-16">
        <div class="max-w-6xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">How Learnmate Helps You</h2>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-blue-600 text-3xl mb-4">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Upload Materials</h3>
                    <p class="text-gray-600">
                        Upload your notes, textbooks, or presentations in any format.
                    </p>
                </div>
                
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-purple-600 text-3xl mb-4">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Get Smart Quizzes</h3>
                    <p class="text-gray-600">
                        Automatically generated quizzes to test your knowledge.
                    </p>
                </div>
                
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="text-blue-600 text-3xl mb-4">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Track Progress</h3>
                    <p class="text-gray-600">
                        See your improvement over time with visual reports.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-6xl mx-auto px-4">
            <div class="md:flex justify-between">
                <div class="mb-8 md:mb-0">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-white text-sm"></i>
                        </div>
                        <span class="ml-2 text-xl font-bold">Learn<span class="gradient-text">mate</span></span>
                    </div>
                    <p class="text-gray-400">
                        Making learning easier for students everywhere.
                    </p>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                    <div>
                        <h3 class="font-semibold mb-4">Product</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-400 hover:text-white">Features</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">Pricing</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">FAQ</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold mb-4">Company</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-400 hover:text-white">About</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">Contact</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white">Privacy</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold mb-4">Connect</h3>
                        <div class="flex space-x-4">
                            <a href="#" class="text-gray-400 hover:text-white text-xl">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white text-xl">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white text-xl">
                                <i class="fab fa-facebook"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-12 pt-8 text-center text-gray-400">
                &copy; <?php echo date("Y"); ?> Learnmate. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>