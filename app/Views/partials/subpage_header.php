<?php
// Ensure active state logic
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!-- Sticky Header mimicking index.php Embedded Toolbar -->
<!-- Note: Requires Tailwind CSS to be loaded in the parent page -->
<section class="bg-white p-0 sticky top-0 z-50 shadow-md rounded-b-lg mb-6 border-b border-gray-200">
    <div class="p-1">
         <div class="flex items-center justify-between gap-1 px-2 py-1">
             
             <!-- Right Side (RTL): Home Button -->
             <div class="flex items-center gap-2">
                 <a href="/" class="flex items-center justify-center gap-2 px-3 h-8 rounded-md text-sm font-bold bg-gray-50 hover:bg-blue-50 text-gray-700 hover:text-blue-700 transition-all border border-gray-200 hover:border-blue-200 shadow-sm" title="العودة للرئيسية">
                     <i data-lucide="home" class="w-4 h-4"></i>
                     <span>الرئيسية</span>
                 </a>
                 
                 <div class="h-5 w-px bg-gray-300 mx-2"></div>
                 
                 <span class="font-bold text-gray-800 text-sm hidden sm:inline-block">نظام إدارة خطابات الضمان</span>
             </div>

             <!-- Left Side (RTL): Navigation Data -->
             <div class="flex items-center gap-1">
                 <!-- Stats Link -->
                 <a href="/stats" 
                    class="flex items-center justify-center gap-2 px-3 h-8 rounded-md transition-all border <?= $currentScript == 'stats.php' ? 'bg-blue-50 text-blue-700 border-blue-200 font-bold shadow-inner' : 'bg-white hover:bg-gray-50 text-gray-600 border-transparent hover:border-gray-200' ?>" 
                    title="الإحصائيات">
                     <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
                    <span class="text-xs">الإحصائيات</span>
                 </a>
                 
                 <!-- Settings Link -->
                 <a href="/settings.php" 
                    class="flex items-center justify-center gap-2 px-3 h-8 rounded-md transition-all border <?= $currentScript == 'settings.php' ? 'bg-blue-50 text-blue-700 border-blue-200 font-bold shadow-inner' : 'bg-white hover:bg-gray-50 text-gray-600 border-transparent hover:border-gray-200' ?>" 
                    title="الإعدادات">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span class="text-xs">الإعدادات</span>
                 </a>
             </div>
         </div>
    </div>
</section>
