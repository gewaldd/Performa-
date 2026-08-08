Performa:



A Web-Based KPI Probationary Employee Evaluation and Training Recommendation Platform for Small Steps Learning Center Using Random Forest Model




Overview

Performa is a web-based platform designed to help small and medium enterprises (SMEs) in the Philippines systematically track, evaluate, and document the performance of probationary employees. The system uses a Random Forest classification model to analyze KPI patterns and identify competency gaps, while the Google Gemini API generates personalized training recommendations and regularization narratives.

The platform is specifically built for SMEs that operate without a dedicated HR department and need an affordable, legally-compliant tool to manage probationary employees within the 180-day deadline mandated by Article 296 of the Philippine Labor Code.
Problem Statement

Small and medium enterprises in the Philippines lack an affordable, structured, and legally compliant system to track, evaluate, and document the performance of probationary employees within the 180-day period required by Article 296 of the Labor Code.
Consequences:

     Missed regularization deadlines – employees become regular by default, even if underperforming

     Inconsistent or missing performance documentation – termination decisions become legally indefensible

     Subjective, memory-based evaluations – leading to bias and unfair decisions

     Costly legal claims (₱50,000–₱150,000 per case) without proper records

     Existing HR tools are too expensive and designed for large corporations

Solution

Performa provides a complete, end-to-end solution for probationary employee evaluation:

     Customizable KPI Tracking – Pre-built templates for 5 industries (retail, BPO, food service, logistics, construction)

     AI-Powered Training Recommendations – Random Forest model identifies competency gaps; Gemini API generates training narratives

     Automated 180-Day Deadline Tracker – Email/SMS alerts at 150, 165, and 178 days

     AI-Assisted Regularization Decisions – Data-driven recommendation at the middle of the fifth month

     PDF Legal Reports – Exportable PDF/A-1b compliant reports for formal documentation

     Employee Acknowledgement – Digital receipt of performance summaries for transparency

     Role-Based Access Control – Separate portals for Employers, Supervisors, and Employees

Features

Employer/Supervisor Modules
Module	Description
Authentication	Secure login for employers, supervisors, and employees
Dashboard	Overview of active employees, deadlines, and quick access to key features
Employee Management	Add, view, and manage employee profiles and assign KPIs
KPI Configuration	Create, customize, and assign KPIs using pre-built industry templates
Performance Rating	Weekly rating entry with grading pop-up modal
Training Recommendations	AI-generated monthly summaries identifying weak areas and training interventions
Regularization Recommendation	AI-assisted decision at the fifth month
Deadline Tracker	Monitors 180-day period with automated alerts
PDF Report Export	Generates exportable PDF performance reports
Settings	Manage account preferences and system configurations
Employee Modules
Module	Description
Dashboard	Performance summary, KPI scores, and recent feedback
Timekeeping & Attendance	Clock in/out, attendance logs, leave requests
Profile	View and update personal information
Performance Summary	Monthly evaluations and AI-generated feedback
Acknowledgement	Digital acknowledgment of performance summaries
Inbox & Notifications	Messages and alerts from employer/supervisor
Settings	Personal preferences (theme, language, notifications)
 
Technology Stack

Frontend
Tool	Purpose
React with JavaScript	User interface development
Figma	UI/UX design and prototyping
Backend & Database
Tool	Purpose
Firebase Authentication	User authentication and role-based access
Firestore Database	Cloud database for storing employee profiles, KPIs, ratings, and reports
Firebase Hosting	Web application hosting
AI & Machine Learning
Tool	Purpose
Random Forest	KPI pattern analysis, competency gap identification, regularization prediction
Google Gemini API	Generating natural language narratives (training recommendations, regularization explanations)
Additional Tools
Tool	Purpose
GitHub	Version control and team collaboration
SendGrid	Email notifications for deadline alerts
Google Lighthouse	Performance testing and optimization
Visual Studio Code	Code editor
