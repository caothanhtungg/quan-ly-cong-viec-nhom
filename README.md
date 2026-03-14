# Quan ly cong viec nhom

Ung dung PHP thuan de quan ly cong viec theo nhom, phan quyen theo 3 vai tro:

- `admin`: quan ly nguoi dung, nhom, tong quan he thong
- `leader`: tao va phan cong cong viec, theo doi bai nop, duyet bai
- `member`: cap nhat tien do, nop file, theo doi cong viec duoc giao

## Cong nghe

- PHP
- SQL Server
- XAMPP / Apache
- Bootstrap 5
- JavaScript

## Cau truc thu muc chinh

- `admin/`: man hinh va chuc nang cho admin
- `leader/`: man hinh va chuc nang cho truong nhom
- `member/`: man hinh va chuc nang cho thanh vien
- `auth/`: dang nhap, dang xuat
- `config/`: cau hinh he thong va ket noi database
- `includes/`: ham dung chung, layout, auth, flash message
- `database/schema.sql`: script tao co so du lieu
- `assets/uploads/submissions/`: noi luu file nop bai

## Tinh nang chinh

- Dang nhap va phan quyen theo vai tro
- Quan ly nhom va thanh vien
- Tao, sua, xoa, giao viec
- Cap nhat tien do cong viec
- Nop file bai lam
- Duyet bai nop va gui thong bao
- Ghi nhat ky hoat dong

## Yeu cau moi truong

- PHP chay duoc voi `sqlsrv` extension
- SQL Server
- Apache thong qua XAMPP

## Cach chay du an

1. Dat source code vao thu muc web, vi du:

```text
d:\xampp\htdocs\task_management
```

2. Tao database bang file SQL:

```text
database/schema.sql
```

3. Tao file cau hinh ket noi:

```text
copy config\database.example.php config\database.php
```

4. Sua `config/database.php` voi thong tin SQL Server cua ban:

- `TASK_DB_SERVER`
- `TASK_DB_NAME`
- `TASK_DB_USER`
- `TASK_DB_PASSWORD`

Hoac sua truc tiep cac gia tri mac dinh trong file.

5. Mo trinh duyet:

```text
http://localhost/task_management
```

## Luu y khi clone repo

- File `config/database.php` khong duoc dua len Git de tranh lo thong tin ket noi.
- Thu muc upload bai nop duoc giu lai, nhung cac file `.docx`/`.pdf` nguoi dung tai len se khong dua len repo.
- Script SQL hien tai la schema tao bang, chua kem du lieu mau.

## Tep can quan tam

- `config/database.example.php`: mau cau hinh database
- `database/schema.sql`: script tao database
- `config/constants.php`: cau hinh `BASE_URL`

## Hinh anh

So do tong quan du an:

- `anh1.drawio.png`
