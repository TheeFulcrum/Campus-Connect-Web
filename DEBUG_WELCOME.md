# Debug Guide for Welcome Message Issue

## Steps to Debug:

1. **Clear Browser Cache:**
   - Press `Ctrl+Shift+Delete` (Windows) or `Cmd+Shift+Delete` (Mac)
   - Clear all cached files and cookies
   - Or use Incognito/Private mode

2. **Open Browser Developer Console:**
   - Press `F12` or `Ctrl+Shift+I` (Windows)
   - Click the "Console" tab

3. **Log In to Campus Connect:**
   - Go to https://campusconnect.ink/login.html
   - Enter your credentials
   - Click Log In

4. **Check the Console Output:**
   Look for lines starting with `DEBUG:` that show:
   
   ```
   DEBUG: Login response: {...}
   DEBUG: User object: {...}
   DEBUG: Username: [your_username]
   DEBUG: Storing cc-welcome-splash: [your_username]
   DEBUG: Retrieved cc-welcome-splash from sessionStorage: [your_username]
   DEBUG: welcomeName= [your_username] safeWelcomeName= [your_username]
   DEBUG: full sessionStorage: ...
   DEBUG: splashLabel= Welcome, <span>[your_username]</span>
   ```

5. **Report What You See:**
   - What does "Username:" show?
   - What does "Storing cc-welcome-splash:" show?
   - What does "full sessionStorage:" show?
   - Does the splash screen show "Welcome undefined" or "Welcome [your_username]"?

## Common Issues:

- **If "Username: NO USER OBJECT"** → The API response doesn't have a `user` field
- **If "Username: undefined"** → The user object exists but `username` is not set
- **If "full sessionStorage:" shows "cc-welcome-splash=undefined"** → The value is being set as the string "undefined"

Share the console output so we can fix it based on the actual API response format!
