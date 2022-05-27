/*
 * データ通信
 *
 */
import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { catchError, mergeMap, Observable, of,timeout } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class DataService {
  
  public AJAX_ROOT = './receive-message.php';
  //public AJAX_ROOT = 'http://localhost/receive-message.php'; //back end for debug 
  public readonly CONNECTION_TIME_OUT = 1000 * 30;

  constructor(private http: HttpClient) {   }

  public postData(params:any):Observable<any> {
    
    const httpOptions = {
      headers: new HttpHeaders({ 'Content-Type': 'application/json' })
    };

    //check cookie
    return this.http.post<any>(this.AJAX_ROOT+'?c=1',{},httpOptions).pipe(
      timeout(this.CONNECTION_TIME_OUT),
      mergeMap((recv:any) => {
        if (recv.hasOwnProperty('result') && recv['result']==true) {
          //send data;
          return this.http.post<any>(this.AJAX_ROOT,params,httpOptions);
        } else {
          return of(false);
        }
      }),
      catchError((err,o)=>{
        console.log(err);
        return of(null);
      })
    ) 
  }
}
