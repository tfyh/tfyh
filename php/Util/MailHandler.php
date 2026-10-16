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

namespace Util;

use Data\Item;

/**
 * This class provides a mail handler for sending mails. It is a wrapper around the php native mailing function.
 */
class MailHandler
{
    /**
     * The String that separates the leading html body from a trailing plain text part. Providing mail in
     * both html and plain text improves its spam rating.
     */
    public string $plainSeparator = "|>>-plain->>|";

    public string $systemMailSender;
    public string $systemMailAddress;
    public string $mailAdmin;

    public string $mailSubjectAcronym;
    public string $mailSubscript;
    public string $mailFooter;

    /**
     * To enable multiple use of a mail address for more than one user, mail addresses may be prefixed by
     * an integer plus '.', e.g. 2.john.doe@nowhere.com for the son of John Doe. This here strips the prefix,
     * if existing.
     * @param String $mailAddress the mail address to be stripped.
     * @return String the stripped mail address.
     */
    public static function stripAddressPrefix (String $mailAddress): String
    {
        $mailParts = explode(".", $mailAddress, 2);
        if (strlen($mailParts[0]) > 1)
            return $mailAddress;
        if (is_numeric($mailParts[0]))
            return $mailParts[1];
        return $mailAddress;
    }


    /**
     * Public Constructor, reads all users. The configuration shall at least contain "system_mail_sender", "mail_admin",
     *            "mail_subscript", "mail_footer";
     */
    public function __construct(Item $mailConfig)
    {
        $this->systemMailSender = $mailConfig->getChild("system_mail_sender")->value();
        $elements = explode("<", $this->systemMailSender, 2);
        $this->systemMailAddress = mb_substr($elements[1], 0, mb_strlen($elements[1]) - 1);
        $this->mailAdmin = $mailConfig->getChild("mail_admin")->value();
        $this->mailSubjectAcronym = $mailConfig->getChild("mail_subject_acronym")->value();
        $this->mailSubscript = $mailConfig->getChild("mail_subscript")->value();
        $this->mailFooter = $mailConfig->getChild("mail_footer")->value();
    }

    /**
     * Check whether the mail should not be sent but stored, because the application is running on the localhost.
     * @return bool true, if the mail shall be stored, false otherwise.
     */
    public function storeToLocalhost(): bool
    {
        return (str_contains(strtolower($_SERVER["SERVER_NAME"]), "localhost"));
    }

    /**
     * Encode a mail header line to quoted printable. Will check for real names in address fields "From:",
     * "Reply-To:", "Cc:", "Bcc:" and encode them. It will always trim the fields and add the "\r\n" sequence
     * for the appropriate Header encoding, according to RFC.
     * @param string $mhLine the mail header line to encode.
     * @return string the encoded mail header line.
     */
    private static function mhLineEncode(string $mhLine): string
    {
        $mhLine = trim($mhLine);
        if (strlen($mhLine) == 0) {
            return "";
        }
        if ((str_contains($mhLine, '<')) && ((strpos($mhLine, "To:") == 0) ||
                (strpos($mhLine, "From:") == 0) || (strpos($mhLine, "Reply-To:") == 0) ||
                (strpos($mhLine, "Cc:") == 0) || (strpos($mhLine, "Bcc:") == 0))) {
            $mheParts = explode("<", $mhLine, 2);
            $mhePParts = explode(":", $mheParts[0], 2);
            if (str_contains($mhePParts[1], '[')) {
                // special support for trailing codes like "John Doe [yahoo-net]" or similar
                $mhePPParts = explode("[", $mhePParts[1], 2);
                $mhlNew = trim($mhePParts[0]) . ": \"=?UTF-8?Q?" .
                    str_replace(" ", "_", quoted_printable_encode(trim($mhePPParts[0]))) . "?= [" .
                    trim($mhePPParts[1]) . "\" <" . trim($mheParts[1]) . "\r\n";
            } else {
                $mhlNew = trim($mhePParts[0]) . ": =?UTF-8?Q?" .
                    str_replace(" ", "_", quoted_printable_encode(trim($mhePParts[1]))) . "?= <" .
                    trim($mheParts[1]) . "\r\n";
            }
        } else {
            $mhlNew = trim($mhLine) . "\r\n";
        }
        return $mhlNew;
    }

    /**
     * Create a plain text alternative by replacing relevant tags and removing the rest
     * @param string $htmlText the html text to be converted.
     * @return string the plain text alternative.
     */
    private function createPlainTextAlternative(string $htmlText): string
    {
        // "\r\n" = End of line type (RFC)
        $replacer = ["<b>" => "*", "</b>" => "*", "<br>" => "\r\n", "<hr>" => "\r\n----------------\r\n",
            "<p>" => "\r\n\r\n", "<h1>" => "\r\n\r\n\r\n\r\n", "<h2>" => "\r\n\r\n\r\n",
            "<h3>" => "\r\n\r\n\r\n", "<h4>" => "\r\n\r\n", "<h5>" => "\r\n\r\n", "<h6>" => "\r\n\r\n"
        ];
        $plainText = $htmlText;
        foreach ($replacer as $search => $replace)
            $plainText = str_replace($search, $replace, $plainText);
        $plainTextSplit = explode("<", $plainText);
        $plainText = "";
        foreach ($plainTextSplit as $plainTextPart)
            if (str_contains($plainTextPart, ">"))
                $plainText .= substr($plainTextPart, strpos($plainTextPart, ">"));
            else
                $plainText .= $plainTextPart;
        return $plainText;
    }

    /**
     * Encode an attachment according to the mail standard.
     * @param string $separator 256 character random string
     * @param string $eol end of line character
     * @param string $location the location of the attachment
     * @return string the encoded attachment
     */
    private function encodeAttachment(string $separator, string $eol, string $location): string
    {
        $body_mixed = "--" . $separator . $eol;
        $content = file_get_contents($location);
        $content = chunk_split(base64_encode($content));
        $filename = !strrpos($location, "/") ? $location : substr($location,
            strrpos($location, "/") + 1);
        $body_mixed .= "Content-Type: application/octet-stream; name=\"$filename\"$eol";
        $body_mixed .= "Content-Transfer-Encoding: base64$eol";
        $body_mixed .= "Content-Disposition: attachment;   filename=\"$filename\"$eol$eol";
        $body_mixed .= $content . $eol;
        return $body_mixed;
    }

    /**
     * Send an email with support for text and HTML content, attachments, and advanced header encoding. Convenience
     * method to wrap the php native mailing method. Will apply proper header and subject encoding as quoted printable
     * and shorten the subject to the maximum allowance of 75 characters.
     *
     * @param string $mailFrom The email address of the sender. Can include a name.
     * @param string $mailReplyTo The email address for reply-to. Should not include a name.
     * @param string $mailTo A comma-separated list of recipient email addresses. The first one is used in the mail() function call.
     * @param string $mailCc A comma-separated list of CC (carbon copy) recipient email addresses.
     * @param string $mailBcc A comma-separated list of BCC (blind carbon copy) recipient email addresses.
     * @param string $subject The subject of the email, supports encoding for special characters.
     * @param string $body The HTML body content of the email. A plain text alternative will be created if not supplied.
     * @param string $attachment1Location Optional location of the first attachment to be included.
     * @param string $attachment2Location Optional location of the second attachment to be included.
     * @return bool True if the email was successfully sent or stored (when running on localhost), false otherwise.
     */
    public function send_mail(string $mailFrom, string $mailReplyTo, string $mailTo, string $mailCc, string $mailBcc,
                              string $subject, string $body, string $attachment1Location = "",
                              string $attachment2Location = ""): bool
    {
        // Mail header encoding.
        // =====================
        // To: can have names, but the very first of them must be placed into the "$mailto" field
        // of the send function call rather than into the mail headers.
        $i = 0;
        $mailHeadersEncoded = "";
        $mailToEncoded = "";
        if (strlen($mailTo) > 0) {
            $mhElements = explode(",", $mailTo);
            foreach ($mhElements as $mhElement) {
                if ($i == 0) {
                    $mailToEncoded = MailHandler::mhLineEncode("To:" . $mhElement);
                    $mailToEncodedParts = explode(":", $mailToEncoded);
                    $mailToEncoded = $mailToEncodedParts[1];
                    // To: is stripped here and added by send method.
                } else {
                    $mailHeadersEncoded .= MailHandler::mhLineEncode("To:" . $mhElement);
                }
                $i++;
            }
        }
        // From may contain a real name but is a single address
        $mailHeadersEncoded .= MailHandler::mhLineEncode("From:" . $mailFrom);
        // Reply-To shall only be a real mail address, no name to be given
        if (strlen($mailReplyTo) > 0) {
            $mailHeadersEncoded .= MailHandler::mhLineEncode("Reply-To:" . $mailReplyTo);
        }
        // To; Cc:, Bcc: Can have names
        if (strlen($mailCc) > 0) {
            $mhElements = explode(",", $mailCc);
            foreach ($mhElements as $mhElement) {
                $mailHeadersEncoded .= MailHandler::mhLineEncode("Cc:" . $mhElement);
            }
        }
        if (strlen($mailBcc) > 0) {
            $mhElements = explode(",", $mailBcc);
            foreach ($mhElements as $mhElement) {
                $mailHeadersEncoded .= MailHandler::mhLineEncode("Bcc:" . $mhElement);
            }
        }

        // Mail subject encoding
        // =====================
        $subject = trim($subject);
        if (str_contains($subject, ']')) {
            // special support for preceding codes like "[yahoo-net] John Doe's alive" or similar
            $subjectParts = explode("]", $subject, 2);
            $qpSubject = trim($subjectParts[0]) . "] =?UTF-8?Q?" . str_replace(" ", "_",
                    str_replace("?", "=3F", // you need to encode '?'
                        quoted_printable_encode(trim($subjectParts[1]))));
        } else {
            // normal subject lines.
            $qpSubject = "=?UTF-8?Q?" . quoted_printable_encode($subject);
        }
        // limit length to 78 characters, encoding characters are not counted
        if (strlen($qpSubject) > 84) {
            $qpSubject = substr($qpSubject, 0, 81) . "...";
        }
        // add encoding trailer.
        $eol = "\r\n"; // End-of-line type (RFC)
        $qpSubject = $qpSubject . "?=" . $eol;

        // find or create plain text
        if (str_contains($body, $this->plainSeparator)) {
            $plain = explode($this->plainSeparator, $body)[1];
            $body = explode($this->plainSeparator, $body)[0];
        } else {
            $plain = $this->createPlainTextAlternative($body);
        }

        // a random hash will be necessary to send mixed content
        $separator = "=_Part_" . md5(time());
        $mailHeadersEncoded .= "MIME-Version: 1.0" . $eol;
        $mailHeadersEncoded .= "Content-Type: multipart/mixed;" . $eol;
        $mailHeadersEncoded .= "    boundary=\"" . $separator . "\"" . $eol . $eol;

        // plain before html
        $body_mixed = "--" . $separator . $eol;
        $body_mixed .= "Content-Type: multipart/alternative;" . $eol;
        $separatorAlternative = "=_Alt_" . md5(time() + 1234567);
        $body_mixed .= "    boundary=\"" . $separatorAlternative . "\"" . $eol . $eol;
        $body_mixed .= "--" . $separatorAlternative . $eol;
        $body_mixed .= "Content-Type: text/plain; charset=\"utf-8\"" . $eol;
        $body_mixed .= "Content-Transfer-Encoding: quoted-printable" . $eol . $eol;
        $body_mixed .= quoted_printable_encode($plain) . $eol;

        // html message
        $body_mixed .= "--" . $separatorAlternative . $eol;
        $body_mixed .= "Content-Type: text/html; charset=\"UTF-8\"" . $eol;
        $body_mixed .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
        $body_mixed .= $body . $eol;
        $body_mixed .= "--" . $separatorAlternative . "--" . $eol . $eol;

        // attachments
        if (strlen($attachment1Location) > 0)
            $body_mixed .= $this->encodeAttachment($separator, $eol, $attachment1Location);
        if (strlen($attachment2Location) > 0)
            $body_mixed .= $this->encodeAttachment($separator, $eol, $attachment2Location);
            $body_mixed .= "--" . $separator . "--" . $eol;

        // Do not send mails when running on "localhost":
        if ($this->storeToLocalhost()) {
            $fName = date("Ymd_His") . "mail.txt";
            $mail_text = $mailToEncoded . "\n\n" . $mailHeadersEncoded . "\n\n" . $qpSubject . "\n\n" .
                $body_mixed . "\n\n";
            $mailSent = file_put_contents("../../var/all_mails_localhost/" . $fName, $mail_text) !== false;
        } else {
            // Send action
            $mailSent = @mail($mailToEncoded, $qpSubject, $body_mixed, $mailHeadersEncoded);
        }
        return $mailSent;
    }
}
