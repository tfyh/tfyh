<?php
/**
 * tools-for-your-hobby
 * https://www.tfyh.org
 * Copyright  2023-2025  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Authentication;

/**
 * Class file for the authentication provider class. The only function it has is the get_pwhash(String
 * $user_id). This here is a dummy stub for any developer to build on.
 */
class AuthProvider
{

    /**
     * This client id is the one which was agreed with the auth provider to authorise the app server for
     * password hash retrieval. 
     */
    private int $clientId;

    /**
     * This client key is the one which was agreed with the auth provider to authorise the app server for
     * password hash retrieval.
     */
    private int $clientKey;

    /**
     * The server to provide the authentication. It is strongly recommended to use https.
     */
    private string $urlApi = "";

    /**
     * The token which represents an error during pw hash retrieval.
     */
    private string $errorToken = "#ERROR#";

    /**
     * public Constructor.
     */
    public function __construct ()
    {
        // enter anything which is needed upon construction here.
    }

    /**
     * get a password hash to validate an entered password for a specific user id. The hash will be
     * verified using the standard password_verify( $entered_data[ "Passwort"], $passwort_hash); method.
     * 
     * @param String $userId
     * @return String password hash or '-' in case of failure. The dummy stub returns "ignore"
     */
    public function getPwHash (String $userId): string
    {
        // add all functionality to get a password hash by your auth provider here.
        // only this dummy stub is allowed to return "ignore" which will cause the password to be looked up in
        // the own database
        return 'ignore';
    }
}
