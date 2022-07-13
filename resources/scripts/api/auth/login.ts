import http from '@/api/http';

export interface LoginResponse {
    complete: boolean;
    intended?: string;
    confirmationToken?: string;
}

export interface LoginData {
    username: string;
    password: string;
    token?: string | null;
    recaptchaData?: string | null;
}

export default ({ username, password, token, recaptchaData }: LoginData): Promise<LoginResponse> => {
    return new Promise((resolve, reject) => {

        if(token) {
            console.log(token);

            http.get('/sanctum/crsf-cookie')
                .then(() => http.post('/auth/login', {
                    token,
                    'g-recaptcha-response': recaptchaData
                }))
                    .then(response => {
                        if(!(response.data instanceof Object)) {
                            return reject('An error occured while processing the login request')
                        }

                        return resolve({
                            complete: response.data.data.complete,
                            intended: response.data.data.intended || undefined,
                            confirmationToken: response.data.data.confirmation_token || undefined,
                        });
                    })
                    .catch(reject)
        } else {
            http.get('/sanctum/csrf-cookie')
            .then(() => http.post('/auth/login', {
                user: username,
                password,
                'g-recaptcha-response': recaptchaData,
            }))
            .then(response => {
                if (!(response.data instanceof Object)) {
                    return reject(new Error('An error occurred while processing the login request.'));
                }

                return resolve({
                    complete: response.data.data.complete,
                    intended: response.data.data.intended || undefined,
                    confirmationToken: response.data.data.confirmation_token || undefined,
                });
            })
            .catch(reject);
        }


    });
};
