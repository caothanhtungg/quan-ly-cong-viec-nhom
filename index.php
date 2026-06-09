<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect_by_role(current_user()['role']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Hệ thống quản lý công việc theo nhóm dành cho Admin, Leader và Member.">
    <title>Task Management - Quản lý công việc theo nhóm</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('/assets/img/hhcc-mark.svg')) ?>">
    <link href="<?= e(asset_url('/assets/css/home.css')) ?>" rel="stylesheet">
</head>
<body class="home-page">
<div class="home-scroll-progress" aria-hidden="true">
    <span data-home-scroll-progress></span>
</div>

<header class="home-header">
    <div class="home-container home-nav">
        <a href="<?= e(base_url('/')) ?>" class="home-brand" aria-label="Task Management - Trang chủ">
            <img src="<?= e(asset_url('/assets/img/hhcc-mark.svg')) ?>" alt="Logo HHCC">
            <span>
                <strong>Task Management</strong>
                <small>HHCC Workspace</small>
            </span>
        </a>

        <button type="button" class="home-menu-toggle" aria-label="Mở menu" aria-expanded="false" data-home-menu-toggle>
            <i class="bi bi-list"></i>
        </button>

        <nav class="home-nav-links" data-home-menu>
            <a href="#features">Tính năng</a>
            <a href="#workflow">Quy trình</a>
            <a href="#roles">Vai trò</a>
            <a href="#about">Đồ án</a>
            <a href="<?= e(base_url('/auth/login.php')) ?>" class="home-btn home-btn-primary home-nav-login">
                Đăng nhập
                <i class="bi bi-arrow-right"></i>
            </a>
        </nav>
    </div>
</header>

<main>
    <section class="home-hero">
        <div class="home-orb home-orb-one"></div>
        <div class="home-orb home-orb-two"></div>

        <div class="home-container home-hero-grid">
            <div class="home-hero-copy" data-home-reveal="left">
                <div class="home-eyebrow">
                    <span class="home-eyebrow-dot"></span>
                    Không gian làm việc tập trung cho đội nhóm
                </div>

                <h1>
                    Điều phối công việc.<br>
                    <span class="home-typewriter-line">
                        <span
                            data-home-typewriter
                            data-words='["Theo dõi tiến độ.","Kết nối đội nhóm.","Kết quả rõ ràng."]'
                        >Theo dõi tiến độ.</span><span class="home-typewriter-cursor" aria-hidden="true"></span>
                    </span><br>
                    Hoàn thành đúng hạn.
                </h1>

                <p>
                    Một hệ thống thống nhất để giao việc, cập nhật tiến độ, nộp bài,
                    phản hồi và theo dõi toàn bộ hoạt động của nhóm.
                </p>

                <div class="home-hero-actions">
                    <a href="<?= e(base_url('/auth/login.php')) ?>" class="home-btn home-btn-primary home-btn-large">
                        Truy cập hệ thống
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                    <a href="#features" class="home-btn home-btn-secondary home-btn-large">
                        Khám phá tính năng
                    </a>
                </div>

                <div class="home-hero-trust">
                    <div class="home-avatar-stack" aria-hidden="true">
                        <span><i class="bi bi-shield-check"></i></span>
                        <span><i class="bi bi-person-workspace"></i></span>
                        <span><i class="bi bi-check2-circle"></i></span>
                    </div>
                    <div>
                        <strong>Phân quyền rõ ràng</strong>
                        <span>Admin, Leader và Member phối hợp trên cùng một hệ thống.</span>
                    </div>
                </div>
            </div>

            <div class="home-product-stage" data-home-reveal="right">
                <div class="home-floating-note home-floating-note-top">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><strong>Đã duyệt bài nộp</strong><small>Cập nhật vừa hoàn tất</small></span>
                </div>

                <div class="home-product-card">
                    <div class="home-product-topbar">
                        <div class="home-product-brand">
                            <img src="<?= e(asset_url('/assets/img/hhcc-mark.svg')) ?>" alt="">
                            <span><strong>HHCC</strong><small>Task Management</small></span>
                        </div>
                        <div class="home-product-user"><i class="bi bi-person-circle"></i> Leader</div>
                    </div>

                    <div class="home-product-body">
                        <aside class="home-product-sidebar">
                            <span class="active"><i class="bi bi-grid-fill"></i></span>
                            <span><i class="bi bi-people-fill"></i></span>
                            <span><i class="bi bi-list-check"></i></span>
                            <span><i class="bi bi-inbox-fill"></i></span>
                        </aside>

                        <div class="home-product-content">
                            <div class="home-product-heading">
                                <span>Tổng quan hôm nay</span>
                                <strong>Tiến độ nhóm</strong>
                            </div>

                            <div class="home-mini-stats">
                                <div><span>Đang làm</span><strong>08</strong><i class="bi bi-arrow-up-right"></i></div>
                                <div><span>Hoàn thành</span><strong>16</strong><i class="bi bi-check2"></i></div>
                                <div><span>Chờ duyệt</span><strong>03</strong><i class="bi bi-hourglass-split"></i></div>
                            </div>

                            <div class="home-progress-panel">
                                <div class="home-progress-head">
                                    <span>Hiệu suất công việc</span>
                                    <strong>72%</strong>
                                </div>
                                <div class="home-chart" aria-hidden="true">
                                    <span style="height: 34%"></span>
                                    <span style="height: 48%"></span>
                                    <span style="height: 42%"></span>
                                    <span style="height: 68%"></span>
                                    <span style="height: 58%"></span>
                                    <span style="height: 82%"></span>
                                    <span style="height: 72%"></span>
                                </div>
                            </div>

                            <div class="home-task-row">
                                <span class="home-task-icon"><i class="bi bi-kanban-fill"></i></span>
                                <span><strong>Hoàn thiện báo cáo</strong><small>Deadline: 12/06/2026</small></span>
                                <em>Đang thực hiện</em>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="home-floating-note home-floating-note-bottom">
                    <span class="home-floating-progress">84%</span>
                    <span><strong>Tiến độ ổn định</strong><small>Công việc được cập nhật liên tục</small></span>
                </div>
            </div>
        </div>

        <div class="home-container home-proof-strip" data-home-reveal>
            <div><strong>01</strong><span>Không gian quản lý tập trung</span></div>
            <div><strong>03</strong><span>Vai trò được phân quyền rõ ràng</span></div>
            <div><strong>06+</strong><span>Luồng nghiệp vụ chính được kết nối</span></div>
            <div><strong>100%</strong><span>Lịch sử hoạt động có thể theo dõi</span></div>
        </div>
    </section>

    <section class="home-marquee" aria-label="Các chức năng chính">
        <div class="home-marquee-track">
            <div>
                <span>Giao việc</span><i></i>
                <span>Theo dõi tiến độ</span><i></i>
                <span>Nộp bài</span><i></i>
                <span>Duyệt kết quả</span><i></i>
                <span>Thông báo</span><i></i>
                <span>Lịch sử hoạt động</span><i></i>
            </div>
            <div aria-hidden="true">
                <span>Giao việc</span><i></i>
                <span>Theo dõi tiến độ</span><i></i>
                <span>Nộp bài</span><i></i>
                <span>Duyệt kết quả</span><i></i>
                <span>Thông báo</span><i></i>
                <span>Lịch sử hoạt động</span><i></i>
            </div>
        </div>
    </section>

    <section class="home-section" id="features">
        <div class="home-container">
            <div class="home-section-heading" data-home-reveal>
                <div>
                    <span class="home-section-kicker">Nền tảng thống nhất</span>
                    <h2>Mọi thứ đội nhóm cần,<br><span class="home-highlight-text" data-home-highlight>trong một luồng làm việc.</span></h2>
                </div>
                <p>
                    Thay vì theo dõi công việc qua nhiều kênh rời rạc, hệ thống tập trung
                    thông tin, tiến độ và phản hồi tại đúng nơi công việc được thực hiện.
                </p>
            </div>

            <div class="home-feature-grid">
                <article class="home-feature-card home-feature-card-large" data-home-reveal>
                    <div class="home-feature-icon"><i class="bi bi-kanban-fill"></i></div>
                    <span class="home-feature-number">01</span>
                    <h3>Giao việc có cấu trúc</h3>
                    <p>
                        Thiết lập người thực hiện, độ ưu tiên, ngày bắt đầu, deadline và trạng thái
                        để mỗi thành viên luôn hiểu rõ trách nhiệm của mình.
                    </p>
                    <div class="home-feature-preview">
                        <div><span class="priority high">Ưu tiên cao</span><strong>Thiết kế giao diện báo cáo</strong></div>
                        <div class="home-feature-progress"><span></span></div>
                        <small><i class="bi bi-calendar3"></i> Còn 2 ngày để hoàn thành</small>
                    </div>
                </article>

                <article class="home-feature-card" data-home-reveal>
                    <div class="home-feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <span class="home-feature-number">02</span>
                    <h3>Theo dõi tiến độ</h3>
                    <p>Cập nhật phần trăm hoàn thành và ghi chú để Leader nắm bắt tình hình.</p>
                    <ul class="home-check-list">
                        <li><i class="bi bi-check2"></i> Nhật ký cập nhật</li>
                        <li><i class="bi bi-check2"></i> Cảnh báo deadline</li>
                    </ul>
                </article>

                <article class="home-feature-card" data-home-reveal>
                    <div class="home-feature-icon"><i class="bi bi-file-earmark-check-fill"></i></div>
                    <span class="home-feature-number">03</span>
                    <h3>Nộp và duyệt bài</h3>
                    <p>Quản lý nhiều phiên bản bài nộp, nhận xét và kết quả duyệt minh bạch.</p>
                    <ul class="home-check-list">
                        <li><i class="bi bi-check2"></i> Lịch sử phiên bản</li>
                        <li><i class="bi bi-check2"></i> Phản hồi trực tiếp</li>
                    </ul>
                </article>

                <article class="home-feature-card home-feature-card-wide" data-home-reveal>
                    <div>
                        <div class="home-feature-icon"><i class="bi bi-bell-fill"></i></div>
                        <span class="home-feature-number">04</span>
                        <h3>Thông báo và lịch sử hoạt động</h3>
                        <p>
                            Các thay đổi quan trọng được ghi nhận, giúp người dùng theo dõi tiến trình
                            và hạn chế bỏ sót thông tin.
                        </p>
                    </div>
                    <div class="home-activity-preview">
                        <div><i class="bi bi-plus-circle-fill"></i><span><strong>Công việc mới được giao</strong><small>Vừa xong</small></span></div>
                        <div><i class="bi bi-upload"></i><span><strong>Thành viên đã nộp V2</strong><small>10 phút trước</small></span></div>
                        <div><i class="bi bi-check-circle-fill"></i><span><strong>Bài nộp đã được duyệt</strong><small>25 phút trước</small></span></div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section home-section-dark" id="workflow">
        <div class="home-container">
            <div class="home-section-heading home-section-heading-light" data-home-reveal>
                <div>
                    <span class="home-section-kicker">Quy trình xuyên suốt</span>
                    <h2>Từ lúc giao việc<br>đến khi hoàn thành.</h2>
                </div>
                <p>Mỗi bước đều có trạng thái, người chịu trách nhiệm và lịch sử để đối chiếu.</p>
            </div>

            <div class="home-workflow" data-home-reveal>
                <article>
                    <span class="home-workflow-index">01</span>
                    <div class="home-workflow-icon"><i class="bi bi-pencil-square"></i></div>
                    <h3>Tạo và giao việc</h3>
                    <p>Leader xác định yêu cầu, thành viên và thời hạn.</p>
                </article>
                <span class="home-workflow-arrow"><i class="bi bi-arrow-right"></i></span>
                <article>
                    <span class="home-workflow-index">02</span>
                    <div class="home-workflow-icon"><i class="bi bi-bar-chart-steps"></i></div>
                    <h3>Cập nhật tiến độ</h3>
                    <p>Member báo cáo tiến trình cùng ghi chú thực hiện.</p>
                </article>
                <span class="home-workflow-arrow"><i class="bi bi-arrow-right"></i></span>
                <article>
                    <span class="home-workflow-index">03</span>
                    <div class="home-workflow-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                    <h3>Nộp kết quả</h3>
                    <p>File được lưu theo phiên bản để dễ dàng kiểm tra.</p>
                </article>
                <span class="home-workflow-arrow"><i class="bi bi-arrow-right"></i></span>
                <article>
                    <span class="home-workflow-index">04</span>
                    <div class="home-workflow-icon"><i class="bi bi-patch-check-fill"></i></div>
                    <h3>Duyệt và hoàn tất</h3>
                    <p>Leader phản hồi, duyệt bài và đóng công việc.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section" id="roles">
        <div class="home-container">
            <div class="home-centered-heading" data-home-reveal>
                <span class="home-section-kicker">Đúng thông tin, đúng người</span>
                <h2>Mỗi vai trò có một không gian làm việc riêng.</h2>
                <p>Quyền truy cập được tổ chức theo trách nhiệm thực tế trong đội nhóm.</p>
            </div>

            <div class="home-role-grid">
                <article class="home-role-card" data-home-reveal>
                    <div class="home-role-top">
                        <span class="home-role-icon"><i class="bi bi-shield-lock-fill"></i></span>
                        <span class="home-role-label">Admin</span>
                    </div>
                    <h3>Quản trị toàn hệ thống</h3>
                    <p>Kiểm soát người dùng, nhóm, công việc và nhật ký hoạt động.</p>
                    <ul>
                        <li>Quản lý tài khoản và phân quyền</li>
                        <li>Tổ chức nhóm và Leader</li>
                        <li>Theo dõi số liệu tổng quan</li>
                    </ul>
                </article>

                <article class="home-role-card home-role-card-featured" data-home-reveal>
                    <div class="home-role-top">
                        <span class="home-role-icon"><i class="bi bi-person-workspace"></i></span>
                        <span class="home-role-label">Leader</span>
                    </div>
                    <h3>Điều phối và đánh giá</h3>
                    <p>Giao việc, theo dõi thành viên, duyệt kết quả và đưa ra phản hồi.</p>
                    <ul>
                        <li>Tạo và phân công công việc</li>
                        <li>Theo dõi hiệu suất nhóm</li>
                        <li>Duyệt bài nộp theo phiên bản</li>
                    </ul>
                </article>

                <article class="home-role-card" data-home-reveal>
                    <div class="home-role-top">
                        <span class="home-role-icon"><i class="bi bi-person-check-fill"></i></span>
                        <span class="home-role-label">Member</span>
                    </div>
                    <h3>Thực hiện và báo cáo</h3>
                    <p>Nắm rõ công việc cá nhân, cập nhật tiến độ và nộp kết quả.</p>
                    <ul>
                        <li>Theo dõi nhiệm vụ được giao</li>
                        <li>Cập nhật tiến độ thực hiện</li>
                        <li>Nộp file và nhận phản hồi</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section home-about-section" id="about">
        <div class="home-container home-about-grid">
            <div class="home-about-copy" data-home-reveal="left">
                <span class="home-section-kicker">Về đồ án</span>
                <h2>Xây dựng từ một bài toán cộng tác thực tế.</h2>
                <p>
                    Hệ thống được phát triển phục vụ đồ án Điện toán đám mây, tập trung vào
                    việc tổ chức quy trình quản lý công việc nhóm rõ ràng, dễ sử dụng và có thể theo dõi.
                </p>
                <div class="home-about-author">
                    <img src="<?= e(asset_url('/assets/img/hhcc-mark.svg')) ?>" alt="Logo HHCC">
                    <span>
                        <strong>Cao Thanh Tùng</strong>
                        <small>MSSV 2924111392</small>
                    </span>
                </div>
            </div>

            <div class="home-about-card" data-home-reveal="right">
                <span class="home-about-card-label">Thông tin học phần</span>
                <div>
                    <small>Đồ án</small>
                    <strong>Điện toán đám mây</strong>
                </div>
                <div>
                    <small>Đơn vị đào tạo</small>
                    <strong>Trường Đại học Kinh doanh và Công nghệ Hà Nội</strong>
                </div>
                <div>
                    <small>Định hướng</small>
                    <strong>Ứng dụng quản lý công việc theo nhóm</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="home-cta-section">
        <div class="home-container">
            <div class="home-cta" data-home-reveal>
                <div>
                    <span class="home-section-kicker">Bắt đầu làm việc</span>
                    <h2>Sẵn sàng truy cập không gian của bạn?</h2>
                    <p>Đăng nhập để tiếp tục quản lý công việc, tiến độ và hoạt động của đội nhóm.</p>
                </div>
                <a href="<?= e(base_url('/auth/login.php')) ?>" class="home-btn home-btn-light home-btn-large">
                    Đi đến trang đăng nhập
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>
</main>

<footer class="home-footer">
    <div class="home-container home-footer-grid">
        <a href="<?= e(base_url('/')) ?>" class="home-brand home-brand-footer">
            <img src="<?= e(asset_url('/assets/img/hhcc-mark.svg')) ?>" alt="Logo HHCC">
            <span><strong>Task Management</strong><small>HHCC Workspace</small></span>
        </a>
        <p>Hệ thống quản lý công việc theo nhóm.</p>
        <a href="<?= e(base_url('/auth/login.php')) ?>">Đăng nhập <i class="bi bi-arrow-up-right"></i></a>
    </div>
</footer>

<script src="<?= e(asset_url('/assets/js/home.js')) ?>"></script>
</body>
</html>
