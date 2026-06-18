# 🚀 RESUME PROMPT - IIT ISM CDC Placement Portal

**Copy and paste this entire prompt into a new Copilot CLI session to continue the project!**

---

## 📍 CONTEXT

I'm building a **complete placement portal for IIT ISM Dhanbad** where:
- Companies register and submit JNF/INF forms
- Admins (CDC) can review, edit, accept, or reject forms
- Email notifications for registration and status updates
- Professional UI with IIT ISM branding

## ✅ WHAT'S ALREADY DONE (Phase 1 - 90% Complete)

**Project Location:** `/Users/nipunkansal/Coding/cdc`
**GitHub:** https://github.com/Nipunk6/CDC

### Backend (Laravel 11) ✅
- Laravel 11 project initialized
- PHP 8.2 & Composer installed
- Laravel Sanctum installed
- Environment configured (.env ready)
- CORS configured for localhost:3000
- Database: SQLite (working) / MySQL ready

### Frontend (Next.js 15) ✅
- Next.js 15 with TypeScript
- MUI v6.5 with custom IIT ISM theme (blue/orange)
- All dependencies installed:
  - ✅ NextAuth.js v5
  - ✅ Axios
  - ✅ React Hook Form + Yup
  - ✅ MUI X DataGrid
  - ✅ Date-fns
- Environment configured (.env.local)

### Documentation ✅
- README.md, SETUP_GUIDE.md
- docs/IMPLEMENTATION.md (100-step roadmap)
- PROJECT_STATUS.md (progress tracking)

### Progress: 9/100 steps complete (9%)

---

## 🎯 YOUR TASK

**Continue implementing the remaining 91 steps (Steps 11-100) from Phase 2 through Phase 10.**

Follow this **STRICT WORKFLOW**:

1. **Read current state:**
   - Check `/Users/nipunkansal/Coding/cdc/PROJECT_STATUS.md`
   - Check SQL database: `SELECT * FROM todos WHERE status = 'pending' ORDER BY id LIMIT 10`

2. **Work incrementally:**
   - Complete each step from the 100-step plan
   - Update SQL after each step: `UPDATE todos SET status = 'done' WHERE id = 'step-XX'`
   - Update PROJECT_STATUS.md after each checkpoint (every 10 steps)

3. **Test as you go:**
   - Test backend API endpoints
   - Test frontend pages
   - Verify integration

4. **Do NOT push to GitHub** unless I explicitly ask
   - Keep all changes local first

---

## 📋 REMAINING PHASES (91 steps)

### Phase 2: Authentication System (Steps 11-20)
- Create users & companies migrations
- Build auth APIs (login, register, logout)
- Setup NextAuth.js
- Create login/register pages
- Implement role-based middleware

### Phase 3: Database & Models (Steps 21-30)
- JNF table migration
- INF table migration
- Model relationships
- Seeders for testing

### Phase 4: Company Portal - Backend (Steps 31-40)
- Company profile APIs
- JNF submission APIs
- INF submission APIs
- File upload handling

### Phase 5: Company Portal - Frontend (Steps 41-50)
- Company dashboard
- JNF form UI (all sections)
- INF form UI (all sections)
- Form validation

### Phase 6: Admin Portal - Backend (Steps 51-60)
- Admin dashboard APIs
- Form review APIs
- Approval/rejection logic
- Company management APIs

### Phase 7: Admin Portal - Frontend (Steps 61-70)
- Admin dashboard
- Form review interface
- Company management UI
- Analytics/reports

### Phase 8: Email & Notifications (Steps 71-80)
- Email templates (registration, approval, rejection)
- Email service integration
- Admin notifications
- Email logs

### Phase 9: Testing & Refinement (Steps 81-90)
- API testing with Postman
- Frontend-backend integration
- Bug fixes
- UI/UX improvements

### Phase 10: Final Polish & Deployment (Steps 91-100)
- Security hardening
- Performance optimization
- Complete documentation
- Deployment preparation

---

## 💻 TECH STACK (MUST USE)

### Frontend
- Next.js (Latest - 15.x)
- MUI v6.5 (Material UI)
- NextAuth.js v5
- Node.js & NPM

### Backend
- Laravel 11
- MySQL/SQLite
- PHP 8.2
- Composer

---

## 📝 IMPLEMENTATION RULES

1. **Work step-by-step** - Complete steps 11, 12, 13... in order
2. **Update SQL database** after each step completion
3. **Update PROJECT_STATUS.md** after every 10 steps (checkpoints)
4. **Test everything** as you implement
5. **Follow Laravel & Next.js best practices**
6. **Use MUI components** for consistent UI
7. **Implement proper error handling**
8. **Add loading states everywhere**
9. **Validate all forms** (frontend & backend)
10. **Keep changes LOCAL** - don't push to GitHub

---

## 🔑 KEY INFORMATION

### Backend URL
`http://localhost:8000`

### Frontend URL
`http://localhost:3000`

### Database
- Current: SQLite (`database/database.sqlite`)
- Target: MySQL (`iitism_placement`)

### Environment Files
- Backend: `/Users/nipunkansal/Coding/cdc/backend/.env`
- Frontend: `/Users/nipunkansal/Coding/cdc/frontend/.env.local`

### MUI Theme Colors (IIT ISM Branding)
- Primary: `#1976d2` (Blue)
- Secondary: `#ff6f00` (Orange)

---

## 📚 REFERENCE DOCUMENTS

1. **100-Step Roadmap:**  
   `/Users/nipunkansal/Coding/cdc/docs/IMPLEMENTATION.md`

2. **Detailed Prompts:**  
   `~/.copilot/session-state/59b84bd5-96a5-4529-a6d5-c64081190518/implementation-prompts.md`

3. **SQL Progress Tracking:**  
   `~/.copilot/session-state/59b84bd5-96a5-4529-a6d5-c64081190518/session.db`
   - Query: `SELECT * FROM todos WHERE phase = X`
   - Update: `UPDATE todos SET status = 'done' WHERE id = 'step-XX'`

---

## 🎬 START HERE

**First actions to take:**

1. **Verify environment:**
   ```bash
   cd /Users/nipunkansal/Coding/cdc
   
   # Check backend
   cd backend
   export PATH="/opt/homebrew/opt/php@8.2/bin:$PATH"
   php artisan --version
   
   # Check frontend
   cd ../frontend
   node --version
   npm --version
   ```

2. **Check current progress:**
   ```sql
   SELECT id, title, status FROM todos WHERE status != 'done' ORDER BY id LIMIT 10;
   ```

3. **Start Phase 2:**
   - Begin with Step 11: Create users migration
   - Continue through Step 20
   - Create checkpoint after Step 20

4. **Continue through all phases** until Step 100 is complete!

---

## 🚨 IMPORTANT REMINDERS

- ✅ Work incrementally (step by step)
- ✅ Test each feature as you build
- ✅ Update SQL database after each step
- ✅ Keep all changes LOCAL (don't push to GitHub)
- ✅ Follow the 100-step roadmap strictly
- ✅ Use MUI components for all UI
- ✅ Implement proper error handling
- ✅ Add loading states and form validation

---

## 💬 GOAL

**Build the COMPLETE, PRODUCTION-READY placement portal with:**
- ✅ Company registration & login
- ✅ JNF & INF form submissions
- ✅ Admin portal for review & approval
- ✅ Email notifications
- ✅ Professional UI/UX with IIT ISM branding
- ✅ Full testing & documentation

**When you finish all 100 steps, the portal should be ready to deploy!**

---

**Now start implementing from Step 11 and complete the entire project! 🚀**
