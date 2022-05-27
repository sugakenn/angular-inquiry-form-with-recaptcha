# angular-inquiry-form-with-recaptcha
Angular project of inquiry form.  optional reCAPTCHAv3.
![app sample image](https://github.com/sugakenn/angular-inquiry-form-with-recaptcha/blob/main/docs/2022y05m27d_161841484.jpg)

## Quick Start
### 1.[copy all files to htdocs(if you use apache server)](dist/inquiry-form)
### 2.[copy receive-message.php to htdocs](src/backend/)
### 3.When someone accesses index.html and sends a message, the message is stored in htdocs/message dir.

## Angular Project
### These codes are Angular project.

## Back end
Use PHP for the backend.Basically, the behavior of this code is just to save the POSTed JSON data as it is.

There are some validations for security reasons.

- Remote Address Check

 - If you create a dir named "access" with htdocs, the number of transmissions will be checked for each IP address.
 You can set the number and interval in the PHP file. And You also can change dir name.

- XSRF Check

- Angular's HttpClient has XSRF validation.So, the implementation is written in PHP.

- reCAPTCHA

Use [ng-recaptcha](https://github.com/DethAriel/ng-recaptcha) 

You can use [Google's reCAPTCHA v3](https://www.google.com/recaptcha/about/) by using ng-recaptcha.

You can use it by getting two passwords from Google and adding them to your code.

The settings are commented out, so please describe them as necessary.
- app.module.ts line(7,18,21)
- app.component.ts line(5,18,79-132)
- receive-message.php line(27-30)
