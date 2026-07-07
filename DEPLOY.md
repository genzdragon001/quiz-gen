# Deploy to Hostinger

## 1. Upload Files
- Upload all files to `public_html/quiz-gen/` (or your preferred subdirectory)
- Use Hostinger File Manager or FTP

## 2. Create Database
- Go to Hostinger -> MySQL Databases
- Create a new database (e.g., `u123456789_quiz`)
- Create a database user with full privileges
- Open phpMyAdmin -> Import -> select `schema.sql`

## 3. Update Config
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_quiz');
define('DB_USER', 'u123456789_quizuser');
define('DB_PASS', 'your-db-password');
```

Edit `config/config.php`:
```php
define('BASE_URL', 'https://yourdomain.com/quiz-gen/');
// Update SMTP settings
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'your-email-password');
define('SMTP_FROM', 'noreply@yourdomain.com');
```

## 4. Install PHPMailer
Option A -- Composer (if SSH available):
```bash
cd public_html/quiz-gen
composer require phpmailer/phpmailer
```

Option B -- Manual:
- Download PHPMailer from https://github.com/PHPMailer/PHPMailer
- Extract `src/` folder into `vendor/phpmailer/phpmailer/src/`
- Create `vendor/autoload.php` or download the whole PHPMailer repo into `vendor/`

## 5. Create Faculty Account
- Visit `https://yourdomain.com/quiz-gen/seed.php`
- Note the credentials
- **DELETE seed.php immediately after**

## 6. Test
- Student portal: `https://yourdomain.com/quiz-gen/student/`
- Faculty login: `https://yourdomain.com/quiz-gen/faculty/login.php`

## 7. Workflow
1. Faculty logs in, creates a quiz (MCQ or True/False)
2. Faculty adds questions with correct answers
3. Faculty marks quiz as Active
4. Faculty adds students to the system
5. Students go to the student portal, enter Student ID + Quiz ID
6. Students enter their email
7. Students take the quiz (one question at a time, with timer + anti-cheat)
8. Score is emailed to the student
9. Faculty views grades and flagged submissions in the Grades dashboard