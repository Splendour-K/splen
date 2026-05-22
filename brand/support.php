<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('brand');

$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';

    if (strlen($subject) < 3 || strlen($message) < 10) {
        $error = 'Please enter a subject and a detailed message.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $subject, $message, $priority]);

        $ticket_id = (int)$pdo->lastInsertId();
        $admin_ids = $pdo->query("SELECT user_id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
        create_notification_batch(
            $admin_ids,
            'New Support Ticket',
            ($brand['brand_name'] ?? 'A brand') . ' opened ticket #' . $ticket_id . ': ' . $subject,
            'system',
            'admin/support-tickets.php',
            'support_ticket',
            $ticket_id
        );

        $success = 'Your support ticket has been created. We will respond shortly.';
    }
}

include '../includes/header.php';
?>

<div class="pt-24 min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row gap-8 py-8">
        <!-- Sidebar -->
        <?php include '../includes/brand_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 space-y-8">
            <header class="p-8 bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm relative overflow-hidden">
                <div aria-hidden="true" class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Help & Support</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">How can we help your brand succeed today?</p>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Contact Methods -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Contact Us</h3>
                    
                    <div class="space-y-4">
                        <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                            <p class="text-[10px] font-black uppercase text-secondary mb-1">Email Support</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">support@splennet.com</p>
                            <p class="text-xs text-gray-500 mt-1">Average response time: 2-4 hours</p>
                        </div>

                        <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                            <p class="text-[10px] font-black uppercase text-secondary mb-1">WhatsApp Support</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">+234 812 345 6789</p>
                            <p class="text-xs text-gray-500 mt-1">Available Mon-Fri, 9am - 5pm</p>
                        </div>
                    </div>
                </section>

                <!-- FAQs -->
                <section class="p-8 bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Quick FAQs</h3>
                    
                    <div class="space-y-4">
                        <details class="group p-4 rounded-xl border border-gray-100 dark:border-gray-800">
                            <summary class="font-bold text-gray-900 dark:text-white cursor-pointer list-none flex justify-between items-center text-sm">
                                How do I pay creators?
                                <span class="group-open:rotate-180 transition-transform">↓</span>
                            </summary>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-4 leading-relaxed">
                                Payments are held in escrow when you hire a creator. Once you approve their content submission, the funds are released to them.
                            </p>
                        </details>

                        <details class="group p-4 rounded-xl border border-gray-100 dark:border-gray-800">
                            <summary class="font-bold text-gray-900 dark:text-white cursor-pointer list-none flex justify-between items-center text-sm">
                                Can I request revisions?
                                <span class="group-open:rotate-180 transition-transform">↓</span>
                            </summary>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-4 leading-relaxed">
                                Yes, you can request revisions based on the "Revision Limit" you set in your campaign brief.
                            </p>
                        </details>

                        <details class="group p-4 rounded-xl border border-gray-100 dark:border-gray-800">
                            <summary class="font-bold text-gray-900 dark:text-white cursor-pointer list-none flex justify-between items-center text-sm">
                                What if the content is stolen?
                                <span class="group-open:rotate-180 transition-transform">↓</span>
                            </summary>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-4 leading-relaxed">
                                All student creators are verified. If you suspect plagiarism, please report it immediately to our support team for investigation.
                            </p>
                        </details>
                    </div>
                </section>
            </div>

            <?php if ($success): ?>
                <div class="p-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl text-green-800 dark:text-green-400 font-bold">
                    ✓ <?php echo e($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="p-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl text-red-800 dark:text-red-400 font-bold">
                    ⚠ <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <section class="p-8 bg-secondary/5 rounded-[2.5rem] border border-secondary/20">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 bg-secondary/10 rounded-full flex items-center justify-center text-2xl">💬</div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Open a Support Ticket</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Priority tickets go straight to the admin team.</p>
                    </div>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Subject</label>
                        <input type="text" name="subject" required minlength="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" placeholder="What do you need help with?">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Priority</label>
                        <select name="priority" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Message</label>
                        <textarea name="message" rows="5" required minlength="10" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" placeholder="Describe the issue or question in detail..."></textarea>
                    </div>
                    <button type="submit" class="px-8 py-3 bg-secondary text-white font-bold rounded-full hover:scale-105 transition shadow-lg shadow-secondary/20">Submit Ticket</button>
                </form>
            </section>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
