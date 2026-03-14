CREATE DATABASE task_management_db;
GO

USE task_management_db;
GO

-- =========================
-- 1. BANG NHOM
-- =========================
CREATE TABLE teams (
    id INT IDENTITY(1,1) PRIMARY KEY,
    team_name NVARCHAR(100) NOT NULL,
    description NVARCHAR(255) NULL,
    leader_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- =========================
-- 2. BANG NGUOI DUNG
-- =========================
CREATE TABLE users (
    id INT IDENTITY(1,1) PRIMARY KEY,
    full_name NVARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL
        CHECK (role IN ('admin', 'leader', 'member')),
    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active', 'inactive')),
    team_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- =========================
-- 3. BANG CONG VIEC
-- =========================
CREATE TABLE tasks (
    id INT IDENTITY(1,1) PRIMARY KEY,
    team_id INT NOT NULL,
    title NVARCHAR(200) NOT NULL,
    description NVARCHAR(MAX) NULL,
    assigned_to INT NOT NULL,
    created_by INT NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium'
        CHECK (priority IN ('low', 'medium', 'high')),
    start_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'not_started'
        CHECK (status IN ('not_started', 'in_progress', 'submitted', 'completed')),
    progress_percent INT NOT NULL DEFAULT 0
        CHECK (progress_percent >= 0 AND progress_percent <= 100),
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT CK_tasks_due_date_range CHECK (due_date >= start_date)
);
GO

-- =========================
-- 4. BANG NHAT KY TIEN DO
-- =========================
CREATE TABLE task_updates (
    id INT IDENTITY(1,1) PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    progress_percent INT NOT NULL
        CHECK (progress_percent >= 0 AND progress_percent <= 100),
    note NVARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- =========================
-- 5. BANG NOP FILE
-- =========================
CREATE TABLE submissions (
    id INT IDENTITY(1,1) PRIMARY KEY,
    task_id INT NOT NULL,
    submitted_by INT NOT NULL,
    file_name NVARCHAR(255) NOT NULL,
    file_path NVARCHAR(255) NOT NULL,
    note NVARCHAR(500) NULL,
    submitted_at DATETIME NOT NULL DEFAULT GETDATE(),
    review_status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (review_status IN ('pending', 'approved', 'rejected')),
    leader_comment NVARCHAR(500) NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    version_no INT NOT NULL DEFAULT 1,
    is_latest BIT NOT NULL DEFAULT 1,
    CONSTRAINT CK_submissions_version_no CHECK (version_no >= 1),
    CONSTRAINT CK_submissions_review_lifecycle CHECK (
        (review_status = 'pending' AND reviewed_by IS NULL AND reviewed_at IS NULL)
        OR
        (review_status IN ('approved', 'rejected') AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL)
    )
);
GO

-- =========================
-- 6. BANG NHAT KY HOAT DONG
-- =========================
CREATE TABLE activity_logs (
    id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    description NVARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- =========================
-- 7. BANG THONG BAO
-- =========================
CREATE TABLE notifications (
    id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NOT NULL,
    title NVARCHAR(150) NOT NULL,
    content NVARCHAR(300) NOT NULL,
    type VARCHAR(50) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    is_read BIT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    read_at DATETIME NULL
);
GO

-- =========================
-- 8. BANG BINH LUAN CONG VIEC
-- =========================
CREATE TABLE task_comments (
    id INT IDENTITY(1,1) PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text NVARCHAR(MAX) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NULL
);
GO

-- =========================
-- 9. BANG LICH SU CONG VIEC
-- =========================
CREATE TABLE task_history (
    id INT IDENTITY(1,1) PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_title NVARCHAR(255) NOT NULL,
    event_description NVARCHAR(MAX) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- =========================
-- 10. KHOA NGOAI
-- =========================
ALTER TABLE users
ADD CONSTRAINT FK_users_team
FOREIGN KEY (team_id) REFERENCES teams(id);
GO

ALTER TABLE teams
ADD CONSTRAINT FK_teams_leader
FOREIGN KEY (leader_id) REFERENCES users(id);
GO

ALTER TABLE tasks
ADD CONSTRAINT FK_tasks_team
FOREIGN KEY (team_id) REFERENCES teams(id);
GO

ALTER TABLE tasks
ADD CONSTRAINT FK_tasks_assigned_to
FOREIGN KEY (assigned_to) REFERENCES users(id);
GO

ALTER TABLE tasks
ADD CONSTRAINT FK_tasks_created_by
FOREIGN KEY (created_by) REFERENCES users(id);
GO

ALTER TABLE task_updates
ADD CONSTRAINT FK_task_updates_task
FOREIGN KEY (task_id) REFERENCES tasks(id);
GO

ALTER TABLE task_updates
ADD CONSTRAINT FK_task_updates_user
FOREIGN KEY (user_id) REFERENCES users(id);
GO

ALTER TABLE submissions
ADD CONSTRAINT FK_submissions_task
FOREIGN KEY (task_id) REFERENCES tasks(id);
GO

ALTER TABLE submissions
ADD CONSTRAINT FK_submissions_submitted_by
FOREIGN KEY (submitted_by) REFERENCES users(id);
GO

ALTER TABLE submissions
ADD CONSTRAINT FK_submissions_reviewed_by
FOREIGN KEY (reviewed_by) REFERENCES users(id);
GO

ALTER TABLE activity_logs
ADD CONSTRAINT FK_activity_logs_user
FOREIGN KEY (user_id) REFERENCES users(id);
GO

ALTER TABLE notifications
ADD CONSTRAINT FK_notifications_user
FOREIGN KEY (user_id) REFERENCES users(id);
GO

ALTER TABLE task_comments
ADD CONSTRAINT FK_task_comments_task
FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE;
GO

ALTER TABLE task_comments
ADD CONSTRAINT FK_task_comments_user
FOREIGN KEY (user_id) REFERENCES users(id);
GO

ALTER TABLE task_history
ADD CONSTRAINT FK_task_history_task
FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE;
GO

ALTER TABLE task_history
ADD CONSTRAINT FK_task_history_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
GO

-- =========================
-- 11. INDEXES
-- =========================
CREATE UNIQUE INDEX UX_submissions_task_user_version
ON submissions (task_id, submitted_by, version_no);
GO

CREATE UNIQUE INDEX UX_submissions_task_user_latest
ON submissions (task_id, submitted_by)
WHERE is_latest = 1;
GO

CREATE INDEX IX_task_comments_task_created_at
ON task_comments (task_id, created_at DESC, id DESC);
GO

CREATE INDEX IX_task_history_task_created_at
ON task_history (task_id, created_at DESC, id DESC);
GO

-- =========================
-- 12. TRIGGERS UPDATED_AT
-- =========================
CREATE TRIGGER TR_teams_set_updated_at
ON teams
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF TRIGGER_NESTLEVEL() > 1
        RETURN;

    UPDATE t
    SET updated_at = GETDATE()
    FROM teams t
    INNER JOIN inserted i ON t.id = i.id;
END
GO

CREATE TRIGGER TR_users_set_updated_at
ON users
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF TRIGGER_NESTLEVEL() > 1
        RETURN;

    UPDATE u
    SET updated_at = GETDATE()
    FROM users u
    INNER JOIN inserted i ON u.id = i.id;
END
GO

CREATE TRIGGER TR_tasks_set_updated_at
ON tasks
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF TRIGGER_NESTLEVEL() > 1
        RETURN;

    UPDATE t
    SET updated_at = GETDATE()
    FROM tasks t
    INNER JOIN inserted i ON t.id = i.id;
END
GO

CREATE TRIGGER TR_task_comments_set_updated_at
ON task_comments
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF TRIGGER_NESTLEVEL() > 1
        RETURN;

    UPDATE tc
    SET updated_at = GETDATE()
    FROM task_comments tc
    INNER JOIN inserted i ON tc.id = i.id;
END
GO
