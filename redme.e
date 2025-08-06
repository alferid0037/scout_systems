Here are some test credentials for different roles that you can use to test the login system. These match the sample data in your database.sql file:

Admin User (Full Access)
Email: admin@ethioscout.org

Username: admin

Password: password

Role: admin

Scout User
Email: scout1@vertwalacademy.com

Username: scout1

Password: password

Role: scout

Coach User
Email: coach1@vertwalacademy.com

Username: coach1

Password: password

Role: coach

Medical Staff User
Email: medical1@vertwalacademy.com

Username: medical1

Password: password

Role: medical

Club Representative (Pending Approval)
Email: club1@vertwalacademy.com

Username: club1

Password: password

Role: club

Important Notes:
All passwords are: password (they're hashed in the database)

The club user will need to upload a license file (PDF/JPEG/PNG) when logging in

The admin user can approve the club license in the admin dashboard

These credentials match the sample data in your database.sql file

How to Test:
Go to login.php?role=admin (or replace with any role)

Enter the email and password for that role

For club role, you'll need to upload a license file

You should be redirected to the appropriate dashboard if credentials are correct

If you need additional test users, you can:

Use the admin dashboard to create new users

Or add more INSERT statements to your database.sql file

Would you like me to provide any specific test cases or scenarios to verify the login functionality?