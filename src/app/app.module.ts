import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { AppComponent } from './app.component';
import { NgbModule } from '@ng-bootstrap/ng-bootstrap';
import { ReactiveFormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
//import { RECAPTCHA_V3_SITE_KEY, RecaptchaV3Module } from "ng-recaptcha";//uncooment if necessary CAPTCHA

@NgModule({
  declarations: [
    AppComponent
  ],
  imports: [
    BrowserModule,
    NgbModule,
    ReactiveFormsModule,
    HttpClientModule,
    //RecaptchaV3Module //uncomment if necessary CAPTCHA
  ],
  providers: [    
   //{ provide: RECAPTCHA_V3_SITE_KEY, useValue: 'YOUR_reCAPTCHA_SITE_KEY' }//uncomment and input your site key if necessary CAPTCHA. 
  ],
  bootstrap: [AppComponent]
})
export class AppModule { }
