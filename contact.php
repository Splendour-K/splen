<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$message) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Store in support_tickets table if available, or log the contact
        try {
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status, created_at) VALUES (NULL, ?, ?, 'open', NOW())");
            $full_subject = $subject ? "[Contact] $subject — $name <$email>" : "[Contact] $name <$email>";
            $full_message = "From: $name ($email)\n\n$message";
            $stmt->execute([$full_subject, $full_message]);
        } catch (Throwable $e) {
            // Table may not exist or have different schema — log silently
            error_log('Contact form submission error: ' . $e->getMessage());
        }
        $sent = true;
    }
}

include 'includes/header.php';
?>

<div class="pt-36 pb-24 bg-gray-50 dark:bg-gray-950 min-h-screen">
    <div class="max-w-5xl mx-auto px-6">

        <!-- Header -->
        <div class="mb-12 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-primary mb-3">Get in touch</p>
            <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">Contact Us</h1>
            <p class="text-gray-500 max-w-xl mx-auto">Have a question, a partnership inquiry, or need help with your account? We typically respond within one business day.</p>
        </div>

        <div class="grid md:grid-cols-5 gap-8">

            <!-- Contact Info -->
            <div class="md:col-span-2 space-y-6">
                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <h3 class="font-black text-gray-900 dark:text-white mb-4">Direct Contact</h3>
                    <div class="space-y-4">
                        <div class="flex gap-3">
                            <span class="w-9 h-9 bg-primary/10 text-primary rounded-xl flex items-center justify-center flex-shrink-0 text-base">✉️</span>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">General</p>
                                <a href="mailto:hello@splennet.com" class="text-sm font-bold text-primary hover:underline">hello@splennet.com</a>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="w-9 h-9 bg-primary/10 text-primary rounded-xl flex items-center justify-center flex-shrink-0 text-base">🤝</span>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Brand Onboarding</p>
                                <a href="mailto:founders@splennet.com" class="text-sm font-bold text-primary hover:underline">founders@splennet.com</a>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="w-9 h-9 bg-primary/10 text-primary rounded-xl flex items-center justify-center flex-shrink-0 text-base">⚖️</span>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Legal & Privacy</p>
                                <a href="mailto:legal@splennet.com" class="text-sm font-bold text-primary hover:underline">legal@splennet.com</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm">
                    <h3 class="font-black text-gray-900 dark:text-white mb-3">FAQs</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">How do I get an invite code?</p>
                            <p class="text-xs text-gray-500 mt-0.5">Email <a href="mailto:founders@splennet.com" class="text-primary">founders@splennet.com</a> with your brand name and website.</p>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">How long does creator verification take?</p>
                            <p class="text-xs text-gray-500 mt-0.5">Typically 1–2 business days after document submission.</p>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">When are creator payouts processed?</p>
                            <p class="text-xs text-gray-500 mt-0.5">Within 5–7 business days of admin approval.</p>
                        </div>
                    </div>
                </div>

                <div class="p-5 bg-gray-900 dark:bg-gray-800 rounded-[2rem] text-white">
                    <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Response Time</p>
                    <p class="text-sm text-gray-300">We aim to respond to all inquiries within <strong class="text-white">1 business day</strong>. For urgent account issues, include "URGENT" in your subject line.</p>
                </div>
            </div>

            <!-- Form -->
            <div class="md:col-span-3">
                <div class="bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 p-8 shadow-sm">

                    <?php if ($sent): ?>
                        <div class="py-16 text-center">
                            <span class="text-5xl block mb-4">✅</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white mb-2">Message received!</h3>
                            <p class="text-gray-500 text-sm">Thanks for reaching out. We'll get back to you within one business day.</p>
                            <a href="<?php echo APP_URL; ?>index.php" class="mt-8 inline-block px-6 py-3 bg-primary text-white font-bold rounded-xl hover:scale-105 transition">Back to Home</a>
                        </div>
                    <?php else: ?>

                        <?php if ($error): ?>
                            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 rounded-xl text-sm text-red-600 dark:text-red-400">
                                <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Your Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required value="<?php echo e($_POST['name'] ?? ''); ?>" placeholder="Kofi Mensah" class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none dark:text-white transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Email Address <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" required value="<?php echo e($_POST['email'] ?? ''); ?>" placeholder="you@example.com" class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none dark:text-white transition">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Subject</label>
                                <input type="text" name="subject" value="<?php echo e($_POST['subject'] ?? ''); ?>" placeholder="e.g. Question about brand onboarding" class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none dark:text-white transition">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Message <span class="text-red-500">*</span></label>
                                <textarea name="message" rows="5" required placeholder="Tell us how we can help..." class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 focus:ring-2 focus:ring-primary outline-none dark:text-white transition resize-none"><?php echo e($_POST['message'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" class="w-full py-3.5 bg-primary text-white font-bold rounded-xl hover:scale-[1.01] transition shadow-lg shadow-primary/20">
                                Send Message →
                            </button>
                            <p class="text-xs text-gray-400 text-center">By submitting this form you agree to our <a href="<?php echo APP_URL; ?>privacy.php" class="text-primary hover:underline">Privacy Policy</a>.</p>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
