# UniHUB-system
UniHUB is a digital university platform for communication within the university environment.
UNIHUB DIGITAL PLATFORM – 
PROJECT DOCUMENTATION 
1. Introduction 
Project Overview 
UniHub is a web-based digital platform designed to connect university students within a secure 
and verified environment. The platform allows users to: 
➢ Join a university-specific hub using their institutional email 
➢ Participate in events 
➢ Buy and sell items within their campus community 
The system enforces strict domain-based authentication to ensure that only verified users access 
their respective university hubs. 
Objectives 
➢ Provide a secure digital environment for university communities 
➢ Enable easy buying and selling 
➢ Support event management, opportunities and participation 
➢ Ensure fast performance and ease of use 
➢ Allow new users to learn and use the system within 3 minutes 
Scope of the System 
The system covers: 
➢ User authentication and role management 
➢ Event creation and participation 
➢ Marketplace (buying and selling within same university) 
➢ Admin controls and monitoring 
2. System Requirements 
Functional Requirements 
➢ System shall users to create accounts using their name, email and password 
➢ User shall type a keyword and see all matching results 
➢ User can add items, change quantities and remove items 
➢ System calculates total and processes mobile payments 
2.2 Non-Functional Requirements 
Usability 
➢ The system shall be simple and intuitive 
➢ A new user should be able to: 
➢ Sign up 
➢ Verify email 
➢ Access dashboard 
➢ Browse listings/events 
within 3 minutes 
➢ Clear navigation menus and buttons 
➢ Minimal clicks (max 3–4 actions to complete a task) 
Performance 
➢ System must load within 8 seconds 
➢ Pages should load in less than 3 seconds after first load 
➢ Optimized images and database queries 
Security 
➢ Email domain validation (e.g., ......@std.must.ac.ug) 
➢ Password encryption using hashing 
➢ Email verification via OTP (One-Time Password) 
➢ User-specific permissions (seller-only edits, admin privileges) 
➢ Special listing security key for sellers 
Reliability 
➢ System available 24/7 
➢ Backup of database 
➢ Error handling for failed operations 
Scalability 
➢ Ability to add multiple universities (multi-hub system) 
➢ Efficient database design for future growth 
3. System Architecture 
Technology Stack 
➢ Frontend: HTML, CSS, JavaScript 
➢ Backend: PHP 
➢ Database: MySQL 
➢ Server: WAMP/XAMPP 
Architecture Design 
➢ Client (Browser) → Frontend (HTML/CSS/JS) 
➢ Server (PHP) handles logic 
➢ Database (MySQL) stores data 
System Flow 
1. User signs up 
2. Email is verified 
3. Domain is checked 
4. User is assigned to university hub 
5. Access granted to: 
➢ Events 
➢ Marketplace 
4. System Modules 
User Management and Authentication 
➢ Email-based signup 
➢ Domain validation 
➢ OTP email verification 
➢ Role-based access (Member/Admin) 
Key Feature: 
Only users with valid university domains can access the hub. 
Events Module 
➢ Admin creates events 
➢ Users view upcoming events 
➢ RSVP functionality 
➢ Event management (edit/delete by admin) 
4.3 Marketplace (BS) Module 
➢ Users create listings (title, price, category, images) 
➢ Listings visible only within same university 
➢ Seller controls: 
➢ Edit 
➢ Delete 
➢ Mark as sold 
Security Enhancement: 
➢ Each seller has a unique listing key 
➢ Prevents unauthorized edits 
Messaging System 
➢ Buyers and sellers communicate per listing 
➢ Conversation stored in database 
➢ Private and secure messaging 
Admin Module 
➢ Dashboard with: 
➢ Total users 
➢ Events 
➢ Listings 
➢ Manage users (edit/delete roles) 
➢ Delete inappropriate listings 
➢ Add new university domains 
5. Database Design (Simplified) 
Main Tables 
➢ Users (id, email, password, role, university_id) 
➢ Universities (id, name, domain) 
➢ Events (id, title, date, location) 
➢ Listings (id, title, price, seller_id, status) 
➢ Messages (id, listing_id, sender_id, message) 
➢ RSVPs (user_id, event_id) 
6. User Interface Design 
Design Principles 
➢ Clean layout 
➢ Easy navigation 
➢ Mobile responsive 
➢ Minimal clutter 
Main Pages 
➢ Home Page 
➢ Sign Up / Login Page 
➢ Dashboard 
➢ Events Page 
➢ Marketplace Page 
➢ Admin Panel 
User Experience Goal 
A new user should: 
1. Register 
2. Verify email 
3. Log in 
4. View marketplace and current event, all in less than two minutes 
7. Performance Optimization 
➢ Use compressed images 
➢ Limit image uploads (max 3 per listing) 
➢ Optimize SQL queries 
➢ Use caching where possible 
➢ Minify CSS and JavaScript 
8. Security Features 
➢ Password hashing (bcrypt) 
➢ Session management 
➢ Email verification 
➢ Domain restriction 
➢ Role-based authorization 
➢ Seller-specific permissions 
9. Testing Strategy 
Types of Testing 
➢ Unit Testing (PHP functions) 
➢ Integration Testing 
➢ User Acceptance Testing (UAT) 
Sample Test Cases 
➢ Invalid email domain → Access denied 
➢ Unverified email → Cannot login 
➢ Non-seller editing listing → Blocked 
➢ RSVP toggle → Works correctly 
10. Deployment 
Tools 
➢ WAMP Server 
➢ phpMyAdmin 
➢ Web browser 
11. Future Improvements 
➢ Mobile App version 
➢ Payment integration (credit card) 
➢ Notification system (SMS/Email) 
➢ AI-based recommendations 
12. Conclusion 
UniHub provides a secure, fast, and user-friendly platform for university students to interact 
digitally. By focusing on: 
➢ Speed (under 8 seconds load) 
➢ Ease of use (under 3 minutes learning) 
➢ Security (domain + verification) 
the system ensures a reliable and scalable solution for campus digital engagement. 
