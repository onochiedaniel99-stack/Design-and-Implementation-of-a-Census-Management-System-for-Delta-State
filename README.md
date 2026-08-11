# Design-and-Implementation-of-a-Census-Management-System-for-Delta-State
A web-based census management platform for Delta State, Nigeria, featuring digital data enumeration, real-time analytics, and secure demographic record tracking.

# 📊 Delta State Census Management System

A secure, mobile-first web application for census data collection across Delta State, Nigeria.

**Live Demo:** [https://delta-census.freedev.app](https://delta-census.freedev.app)

**Test Credentials:**
- Username: `Admin`
- Password: `Danito`

> ⚠️ **Note**: This is a demonstration instance. Please use responsibly.

---

## ✨ Features

- **GPS Location Tracking** - Every submission includes coordinates with accuracy verification
- **Photo Capture** - Upload individual and operator photographs
- **Mobile Optimized** - Works smoothly on smartphones and tablets
- **Role-Based Access** - Separate interfaces for Admins and Field Operators
- **Real-Time Analytics** - Interactive charts and dashboards
- **Data Export** - Export reports to Excel and PDF

---

## 🛠️ Tech Stack

- **Backend:** Core PHP 8+ (OOP), MySQL
- **Frontend:** Bootstrap 5, JavaScript, Chart.js
- **Services:** Browser Geolocation API, Google Maps

---

## 📂 Project Structure

```
deltacensus/
├── config/          # Database configuration
├── controllers/     # Business logic
├── models/          # Database operations
├── views/           # Templates
├── includes/        # Helper functions
├── uploads/         # File uploads
└── public/          # Public entry point
```

---

## 🚀 Quick Start

### 1. Clone Repository
```bash
git clone https://github.com/onochiedaniel99-stack/Design-and-Implementation-of-a-Census-Management-System-for-Delta-State.git
cd delta-census
```

### 2. Setup Database
```sql
CREATE DATABASE deltacensus3;
-- Import database/schema.sql
```

### 3. Configure
Update `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'deltacensus3');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Permissions
```bash
chmod -R 755 uploads/
```

### 5. Run
Point your web server to the `public` directory.

---

## 🔑 Key Features

| Feature | Description |
|---------|-------------|
| 📍 GPS | Latitude, longitude, accuracy tracking |
| 📸 Photos | Secure image upload for individuals & operators |
| 📊 Analytics | Gender, age, LGA distribution charts |
| 📱 Mobile | Touch-friendly interface |
| 🔒 Security | CSRF protection, password hashing, PDO |

---

## 👥 User Roles

### Administrator
- Manage operators and assignments
- View all census records
- Generate reports
- Monitor activities

### Field Operator
- Register households
- Register individuals
- GPS verification
- Photo uploads

---

## 📊 Database Tables

- `users` - System users
- `lgas` - Local Government Areas
- `wards` - Wards
- `households` - Registered households
- `individuals` - Registered persons
- `activity_logs` - Audit trail

---

## 🔒 Security

- Password hashing (`password_hash()`)
- SQL prepared statements (PDO)
- CSRF token validation
- Session security
- File upload validation

---

## 🤝 Contributing

1. Fork the repo
2. Create feature branch
3. Commit changes
4. Push to branch
5. Open Pull Request

---

## 📝 License

MIT License - free to use and modify.

---

## 🌐 Hosting

This application is hosted and accessible at:

**[https://delta-census.freedev.app](https://delta-census.freedev.app)**

---

## 📞 Contact

**Maintainer:** Your Name
- GitHub: [@onochiedaniel99-stack](https://github.com/onochiedaniel99-stack)
- Email: onochiedaniel99@gmail.com

---

## ⭐ Support

If you find this useful, please give it a star ⭐

---

**Built for efficient census data collection in Delta State, Nigeria**
