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
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
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
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
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
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
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
    created_at DATETIME DEFAULT GETDATE()
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
    submitted_at DATETIME DEFAULT GETDATE(),
    review_status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (review_status IN ('pending', 'approved', 'rejected')),
    leader_comment NVARCHAR(500) NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL
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
    created_at DATETIME DEFAULT GETDATE()
);
GO

-- =========================
-- 7. KHOA NGOAI
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

IF OBJECT_ID('notifications', 'U') IS NULL
BEGIN
    CREATE TABLE notifications (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        title NVARCHAR(150) NOT NULL,
        content NVARCHAR(300) NOT NULL,
        type VARCHAR(50) NOT NULL,
        reference_type VARCHAR(50) NULL,
        reference_id INT NULL,
        is_read BIT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT GETDATE(),
        read_at DATETIME NULL
    );

    ALTER TABLE notifications
    ADD CONSTRAINT FK_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id);
END
GO
