<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<div class="pt-36 pb-24 bg-gray-50 dark:bg-gray-950 min-h-screen">
    <div class="max-w-4xl mx-auto px-6">

        <!-- Header -->
        <div class="mb-10">
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-primary mb-3">Legal</p>
            <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">Terms of Use</h1>
            <p class="text-gray-500 text-sm">Last updated: <strong>May 26, 2026</strong> &mdash; Effective immediately upon registration.</p>
        </div>

        <!-- Intro card -->
        <div class="mb-8 p-6 bg-primary/5 border border-primary/20 rounded-2xl">
            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                These Terms of Use ("<strong>Terms</strong>") govern your access to and use of Splennet, a UGC marketplace operated by Splennet ("<strong>we</strong>", "<strong>us</strong>", or "<strong>the Company</strong>"). By registering an account or using the Service in any way, you confirm that you have read, understood, and agree to be bound by these Terms and our <a href="<?php echo APP_URL; ?>privacy.php" class="text-primary hover:underline font-semibold">Privacy Policy</a>. If you do not agree, you must not access or use the Service.
            </p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 p-8 md:p-12 shadow-sm space-y-10">

            <!-- 1 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">1</span>
                    The Platform & Its Role
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p>Splennet is a marketplace that facilitates connections between brands seeking user-generated content and verified student creators. We provide tools for campaign briefs, contest management, UGC orders, job applications, in-app messaging, escrow-based payments, and CPM performance tracking.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">Splennet is not an agency</strong> and does not employ creators. We are a technology platform and intermediary. Brands engage creators directly; Splennet provides the infrastructure and process guardrails for those engagements. All work arrangements are between brands and creators; Splennet does not guarantee the performance, quality, or outcome of any engagement.</p>
                </div>
            </section>

            <!-- 2 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">2</span>
                    Eligibility
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">2.1 Age.</strong> You must be at least 18 years old (or the age of majority in your jurisdiction) to register an account. By creating an account, you represent that you meet this requirement.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">2.2 Creator Verification.</strong> Creator accounts are exclusively available to individuals currently enrolled at a recognized tertiary institution (university, polytechnic, or equivalent). Creators must complete the student verification process by submitting valid documentation. Providing false or misleading verification documents will result in immediate account suspension and may be reported to relevant authorities.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">2.3 Brand Eligibility.</strong> Brand accounts may be created by businesses, companies, or individuals marketing a product or service. By registering as a brand, you represent that you have the legal authority to enter into these Terms on behalf of your organization.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">2.4 One Account Per Person.</strong> You may hold one Creator account and/or one Brand account. Creating duplicate accounts to circumvent restrictions or bans is prohibited.</p>
                </div>
            </section>

            <!-- 3 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">3</span>
                    Account Responsibilities
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">3.1 Account Security.</strong> You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account. Notify us immediately at <a href="mailto:support@splennet.com" class="text-primary hover:underline">support@splennet.com</a> if you suspect unauthorized access to your account.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">3.2 Accurate Information.</strong> You agree to provide accurate, current, and complete information during registration and to keep your profile information up to date. Splennet is not liable for problems arising from inaccurate information you provide.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">3.3 Non-Transferability.</strong> Your account is personal and non-transferable. You may not sell, transfer, or assign your account or any rights under it to another person.</p>
                </div>
            </section>

            <!-- 4 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">4</span>
                    Campaign, Contest & UGC Order Rules
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">4.1 Brand Obligations.</strong> Brands must provide clear, accurate, and lawful campaign briefs. Brands agree not to solicit illegal content, content that infringes third-party rights, or content that is deceptive, misleading, or harmful. Brands are solely responsible for the accuracy of their product claims and any regulatory compliance obligations (e.g., advertising disclosure requirements).</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">4.2 Creator Obligations.</strong> Creators agree to submit only original content that they own and have the right to license. Creators must not submit content that violates third-party intellectual property rights, contains defamatory or hateful material, depicts illegal activity, or was produced under false pretenses. Creators are responsible for obtaining any necessary consents from individuals appearing in their content.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">4.3 Content Standards.</strong> All content submitted through the platform must comply with our Content Policy. Splennet reserves the right to remove any content that violates these Terms, at our sole discretion and without prior notice.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">4.4 Contest Integrity.</strong> Contest entries must be submitted only by the individual account holder. Multiple entries from the same creator, collusion between contestants, artificial view inflation, or any attempt to manipulate contest outcomes is prohibited and will result in disqualification and potential account suspension.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">4.5 View Count Verification.</strong> For CPM campaigns, creators are required to submit verifiable proof of video performance (screenshots, analytics exports). Submission of falsified, edited, or misleading performance data constitutes fraud and will result in immediate disqualification and account termination, with any paid amounts subject to recovery.</p>
                </div>
            </section>

            <!-- 5 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">5</span>
                    Payments, Fees & Escrow
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">5.1 Brand Wallet & Escrow.</strong> Brands are required to maintain a funded wallet on the platform. When a campaign, contest, or UGC order is launched, the applicable budget is reserved in the brand's wallet. Funds are held in escrow and only released to creators upon brand approval of submitted content or upon the completion of an approved payout cycle.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">5.2 Platform Service Fee.</strong> Splennet charges a platform service fee of <strong>15%</strong> on the total value of each successfully completed campaign. This fee is deducted at the time of payout processing. No service fee is charged on returned or cancelled campaigns where no content was approved.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">5.3 Creator Payouts.</strong> Creators receive payment for approved content submissions, contest wins, and CPM performance milestones. All payouts are subject to administrative review and approval before disbursement. Payouts are processed via the payout method on file (e.g., mobile money, bank transfer) and are typically processed within 5–7 business days of approval.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">5.4 CPM Payouts.</strong> CPM (Cost Per Mille) payouts are calculated based on verified view counts submitted by creators and reviewed by Splennet administrators. The applicable CPM rate is set per campaign by the brand. Splennet's determination of verified view counts is final.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">5.5 Refunds & Disputes.</strong> Brands may request a refund of unused wallet funds that have not been reserved against an active campaign. Once funds are reserved for an active campaign and a creator has been approved to begin work, the reservation is non-refundable unless Splennet determines otherwise following a dispute resolution process. Creators who receive payment for content subsequently found to violate these Terms may be required to return those funds.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">5.6 Taxes.</strong> Each party is responsible for their own tax obligations arising from use of the Service. Splennet does not provide tax advice and is not responsible for any tax liabilities incurred by users.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">5.7 Currency.</strong> Transaction amounts are denominated in the currency specified at the time of campaign creation. Exchange rate fluctuations, international transfer fees, and third-party payment provider charges are the sole responsibility of the user.</p>
                </div>
            </section>

            <!-- 6 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">6</span>
                    Intellectual Property & Content Ownership
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">6.1 Creator Ownership.</strong> Creators retain ownership of the original content they produce until the rights are expressly transferred or licensed per the terms of a campaign brief. By submitting content on the platform, creators grant Splennet a limited, non-exclusive license to display and host the content for the purpose of facilitating the campaign workflow.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">6.2 Brand License.</strong> Upon approval of a creator's content submission and completion of payment, the brand receives the rights as specified in the campaign brief (typically a non-exclusive or exclusive license to use the content for digital marketing purposes). The scope of the license is determined by what is stated in the campaign brief at the time of application. Brands are responsible for ensuring their brief accurately describes the rights they require.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">6.3 Splennet IP.</strong> The Splennet platform, including its design, software, trademarks, and all platform-generated content, is owned by Splennet and protected by applicable intellectual property laws. You may not copy, reproduce, or create derivative works from our platform without prior written permission.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">6.4 No Infringing Content.</strong> You must not submit content that infringes the copyright, trademark, or other intellectual property rights of any third party. Splennet will cooperate with rights holders and remove infringing content upon receipt of a valid takedown notice.</p>
                </div>
            </section>

            <!-- 7 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">7</span>
                    Prohibited Conduct
                </h2>
                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p class="mb-3">You agree not to engage in any of the following:</p>
                    <ul class="list-disc list-inside space-y-2 ml-2">
                        <li>Posting false, misleading, or fraudulent information on your profile or in campaign briefs;</li>
                        <li>Submitting content containing hate speech, harassment, explicit material, or content that promotes violence or discrimination;</li>
                        <li>Circumventing the platform's payment system by arranging off-platform transactions to avoid service fees;</li>
                        <li>Artificially inflating view counts, engagement metrics, or contest votes through bots, paid services, or any inauthentic means;</li>
                        <li>Impersonating another user, brand, or entity;</li>
                        <li>Using automated tools (scrapers, bots, crawlers) to access or extract data from the platform;</li>
                        <li>Attempting to gain unauthorized access to other users' accounts or Splennet's backend systems;</li>
                        <li>Uploading malware, viruses, or any malicious code;</li>
                        <li>Harassing, threatening, or abusing other users through the platform's messaging system;</li>
                        <li>Using the platform to facilitate any illegal activity, including money laundering or tax evasion;</li>
                        <li>Violating any applicable local, national, or international law or regulation.</li>
                    </ul>
                    <p class="mt-4">Violations may result in immediate account suspension, forfeiture of wallet balance, and referral to law enforcement where appropriate.</p>
                </div>
            </section>

            <!-- 8 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">8</span>
                    Dispute Resolution
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">8.1 Platform-Facilitated Resolution.</strong> Disputes between brands and creators regarding content quality, deliverables, or payment should first be submitted to Splennet through the platform's dispute mechanism. Splennet will review the dispute and issue a determination within 10 business days. Splennet's determination is final and binding on both parties.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">8.2 Good Faith Effort.</strong> Both parties agree to participate in good faith in the dispute resolution process, including providing requested documentation and evidence promptly.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">8.3 Escalation.</strong> If a dispute cannot be resolved through the platform process, the parties may pursue other legal remedies. However, both parties agree to first exhaust the platform's resolution process before escalating.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">8.4 No Class Actions.</strong> To the extent permitted by applicable law, all disputes must be resolved on an individual basis and not as part of a class or collective action.</p>
                </div>
            </section>

            <!-- 9 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">9</span>
                    Account Suspension & Termination
                </h2>
                <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">9.1 Termination by You.</strong> You may close your account at any time by contacting our support team. Account closure does not automatically entitle you to a refund of any reserved wallet funds, outstanding campaign budgets, or pending payouts under active engagements.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">9.2 Termination by Splennet.</strong> We may suspend or terminate your account at any time, with or without notice, if we determine that you have violated these Terms, engaged in fraudulent activity, pose a security risk to other users, or for any other reason at our discretion. Upon termination, your right to access the platform ceases immediately.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">9.3 Effect of Termination.</strong> Upon account termination, all outstanding campaign obligations remain binding. Splennet will attempt to fulfill pending creator payouts that were earned prior to termination, subject to verification and compliance review. Unused brand wallet funds will be refunded after any deductions for outstanding obligations.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">9.4 Survival.</strong> Sections 5 (Payments), 6 (Intellectual Property), 10 (Limitation of Liability), and 12 (Governing Law) survive account termination.</p>
                </div>
            </section>

            <!-- 10 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">10</span>
                    Disclaimer of Warranties
                </h2>
                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p>THE SERVICE IS PROVIDED ON AN "AS IS" AND "AS AVAILABLE" BASIS WITHOUT WARRANTIES OF ANY KIND, WHETHER EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT. SPLENNET DOES NOT WARRANT THAT THE SERVICE WILL BE UNINTERRUPTED, ERROR-FREE, OR FREE OF HARMFUL COMPONENTS. WE DO NOT GUARANTEE THAT BRANDS WILL RECEIVE CONTENT MEETING THEIR EXPECTATIONS OR THAT CREATORS WILL RECEIVE A PARTICULAR VOLUME OF JOB OPPORTUNITIES OR EARNINGS.</p>
                </div>
            </section>

            <!-- 11 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">11</span>
                    Limitation of Liability
                </h2>
                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed space-y-4">
                    <p>TO THE FULLEST EXTENT PERMITTED BY APPLICABLE LAW, SPLENNET AND ITS OFFICERS, DIRECTORS, EMPLOYEES, AND AGENTS SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING LOSS OF PROFITS, LOSS OF DATA, LOSS OF GOODWILL, OR SERVICE INTERRUPTION, ARISING FROM OR IN CONNECTION WITH YOUR USE OF THE SERVICE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.</p>
                    <p>IN NO EVENT SHALL OUR TOTAL LIABILITY TO YOU FOR ALL CLAIMS RELATING TO THE SERVICE EXCEED THE GREATER OF (A) THE TOTAL AMOUNT YOU PAID TO SPLENNET IN THE 12 MONTHS PRECEDING THE CLAIM, OR (B) ONE HUNDRED GHANA CEDIS (GHS 100).</p>
                    <p>Some jurisdictions do not allow the exclusion or limitation of liability for consequential or incidental damages, so the above limitation may not apply to you to the extent prohibited by applicable law.</p>
                </div>
            </section>

            <!-- 12 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">12</span>
                    Governing Law
                </h2>
                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p>These Terms are governed by and construed in accordance with the laws of the Republic of Ghana, without regard to its conflict of law principles. You agree that any legal action or proceeding arising under these Terms shall be brought exclusively in the courts of competent jurisdiction in Ghana, and you consent to personal jurisdiction in such courts.</p>
                </div>
            </section>

            <!-- 13 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">13</span>
                    Changes to These Terms
                </h2>
                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p>We may revise these Terms from time to time. When we do, we will update the "Last updated" date and notify you via email or in-app notification for material changes. By continuing to use the Service after changes take effect, you agree to the revised Terms. If you do not agree to the updated Terms, you must stop using the Service and may close your account.</p>
                </div>
            </section>

            <!-- 14 -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">14</span>
                    Miscellaneous
                </h2>
                <div class="space-y-3 text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p><strong class="text-gray-800 dark:text-gray-200">Entire Agreement.</strong> These Terms, together with our Privacy Policy, constitute the entire agreement between you and Splennet regarding the Service and supersede all prior agreements.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">Severability.</strong> If any provision of these Terms is found to be unenforceable, the remaining provisions will continue in full force and effect.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">Waiver.</strong> Failure by Splennet to enforce any right or provision under these Terms does not constitute a waiver of that right or provision.</p>
                    <p><strong class="text-gray-800 dark:text-gray-200">Assignment.</strong> You may not assign or transfer any of your rights or obligations under these Terms without our prior written consent. Splennet may assign these Terms freely.</p>
                </div>
            </section>

            <!-- 15 Contact -->
            <section>
                <h2 class="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">15</span>
                    Contact
                </h2>
                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">
                    <p>If you have questions about these Terms, please contact us:</p>
                    <div class="mt-4 p-5 bg-gray-50 dark:bg-gray-800 rounded-2xl space-y-1">
                        <p class="font-bold text-gray-800 dark:text-gray-200">Splennet Legal Team</p>
                        <p>Email: <a href="mailto:legal@splennet.com" class="text-primary hover:underline">legal@splennet.com</a></p>
                        <p>Support: <a href="<?php echo APP_URL; ?>contact.php" class="text-primary hover:underline">Contact form</a></p>
                    </div>
                </div>
            </section>

        </div>

        <!-- Footer nav -->
        <div class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
            <a href="<?php echo APP_URL; ?>privacy.php" class="text-primary font-bold hover:underline">→ Read our Privacy Policy</a>
            <a href="<?php echo APP_URL; ?>contact.php" class="text-gray-500 hover:text-primary transition">Questions? Contact us</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
