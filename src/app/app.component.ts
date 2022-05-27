import { Component,OnInit} from '@angular/core';
import { AbstractControl, FormBuilder, FormGroup, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
import { catchError, concatMap, of } from 'rxjs';
import { DataService } from './data.service';
//import { ReCaptchaV3Service } from 'ng-recaptcha';//uncomment if necessary CAPTCHA

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit{
  title = 'inquiry-form';

  constructor(
    private dataSv: DataService ,
    private fb: FormBuilder,
    //private cap: ReCaptchaV3Service //uncomment if necessary CAPTCHA
  ) { }
  public intStep: number = 0;
  public messages:string[]=[];

  public form : FormGroup= this.fb.group({
    nicname:['',Validators.required],
    mail:['',regExValidator(/^[A-Za-z0-9]{1}[A-Za-z0-9_.-]*@{1}[A-Za-z0-9_.-]+.[A-Za-z0-9]+$/,'メールアドレスが不正です')],
    hp:[''],
    summary:['',Validators.required],
    detail:['',Validators.required]
  });

  public strNext:string='　';
  public strPrev:string='　';
  public blnSend = false;

  ngOnInit(): void {
    
    setTimeout(()=>{this.setButton()});      
  }

  public clear(strLastMsg:string[]):void {
    
    this.form.controls['nicname'].reset('');
    this.form.controls['mail'].reset('');
    this.form.controls['hp'].reset('');
    this.form.controls['summary'].reset('');
    this.form.controls['detail'].reset('');
    
    this.messages=[];
    for (let i = 0; i < strLastMsg.length; i++) {
      this.messages.push(strLastMsg[i]);
    }
  }

  public next():void {
    switch(this.intStep) {
    case 0:
      this.intStep=1;
      this.setButton();
      window.scrollTo(0, 0);
      this.form.disable();  
      

      break;
    case 1:
      //送信
      this.blnSend = true;
     
      let strActionName='submit';

      let params = {
        nicname: this.form.controls['nicname'].value,
        mail: this.form.controls['mail'].value,
        hp: this.form.controls['hp'].value,
        summary: this.form.controls['summary'].value,
        detail: this.form.controls['detail'].value,
        token:''
      };

      // if necessary CAPTCHA, Please replace with the description below
      this.dataSv.postData(params).subscribe(v=>{
        if (v.hasOwnProperty('result')) {
          if (v['result']==true) {
            //クリア
            this.intStep=0;
            this.clear(['データを送信しました。','Data has been sent.']);
            this.form.disable();
          } else {
            this.blnSend = false;
            this.messages=[];
            this.messages.push('error:'+v['message']);
          }
        } else {
          this.blnSend = false;
          this.messages=[];
          this.messages.push('その他のエラーが発生しました');
          this.messages.push('Other errors have occurred');
        }
      });

      /***** if necessary CAPTCHA *********************** */
      /*  
      let unsub = this.cap.execute(strActionName).pipe(
        concatMap((v:string)=>{
          params['token']=v;
          return this.dataSv.postData(params)
        }),
        catchError((err:any)=> {
          console.log(err);
          return of({result:false,message:'token error'});
        })
      ).subscribe(v=>{
        if (v.hasOwnProperty('result')) {
          if (v['result']==true) {
            //クリア
            this.intStep=0;
            this.clear(['データを送信しました。','Data has been sent.']);
            this.form.disable();
          } else {
            this.blnSend = false;
            this.messages=[];
            this.messages.push('error:'+v['message']);
          }
        } else {
          this.blnSend = false;
          this.messages=[];
          this.messages.push('その他のエラーが発生しました');
          this.messages.push('Other errors have occurred');
        }
        unsub.unsubscribe();
      });
      */
      /***************************************************************/

      break;
    default:

    }
  }

  public prev():void {
    switch(this.intStep) {
    case 0:
      this.clear(['クリアしました','Cleared']);
      break;
    case 1:
      this.intStep=0;
      this.setButton();
      this.form.enable();  
      break;
    default:

    }
  }

  public setButton():void {
    switch(this.intStep) {
    case 0:
      this.strNext='確認へ:Next';
      this.strPrev='クリア:Clear';
      break;
    case 1:
      this.strNext='確定:Send';
      this.strPrev='修正:Cancel';
      break;
    default:

    }
  }
}
export function regExValidator(regEx: RegExp, strErrMsg: string):ValidatorFn {
  return (control: AbstractControl):ValidationErrors | null => {
    const result = regEx.test(control.value);
      return result ? null : { message: strErrMsg };
  }
}