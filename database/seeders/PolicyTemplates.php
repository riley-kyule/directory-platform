<?php

namespace Database\Seeders;

/**
 * Generic, portable legal-policy starting content for a listings/directory
 * platform. Written to be reusable across similarly-shaped platforms via the
 * {{platform_name}} / {{support_email}} tokens (substituted at render time by
 * PolicyVersion::renderedContent()) rather than hardcoded business specifics.
 *
 * This is a reasonable starting draft, not a substitute for review by
 * qualified legal counsel in the operator's jurisdiction — particularly for
 * an adult-services directory, where age-verification, advertising, and
 * anti-trafficking law vary sharply by region and change over time.
 */
class PolicyTemplates
{
    /** @return array<string, array{version: string, title: string, summary: string, content: string, requires_reacceptance: bool}> */
    public static function all(): array
    {
        return [
            'terms' => [
                'version' => '1.0',
                'title' => 'Terms of Use',
                'summary' => 'The rules for accessing and using {{platform_name}}.',
                'requires_reacceptance' => true,
                'content' => <<<'MD'
                ## 1. Acceptance of these Terms

                These Terms of Use ("Terms") govern access to and use of {{platform_name}} (the "Platform"), including its website, listings, and any related services. By accessing or using the Platform, you agree to be bound by these Terms. If you do not agree, do not use the Platform.

                ## 2. Eligibility

                You must be at least 18 years old, or the age of majority in your jurisdiction if higher, to access or use the Platform. By using the Platform you represent and warrant that you meet this requirement. The Platform is not directed at, and must not be used by, anyone under this age.

                ## 3. Nature of the Platform

                The Platform is a directory that allows independent providers and agencies to publish listings and allows visitors to browse and contact them. The Platform does not employ, represent, or vouch for any provider, and is not a party to any arrangement, appointment, or transaction between a visitor and a listed provider. Providers are independent third parties solely responsible for their own conduct, listings, and any services they offer.

                ## 4. Account Responsibilities

                If you create an account, you are responsible for maintaining the confidentiality of your credentials and for all activity under your account. You agree to provide accurate information and to keep it up to date. Notify {{support_email}} promptly if you suspect unauthorized use of your account.

                ## 5. Acceptable Use

                You agree not to:

                - Use the Platform for any unlawful purpose, or to facilitate trafficking, coercion, or the exploitation of any person;
                - Post, upload, or transmit content involving anyone under 18 years old, in any context;
                - Impersonate any person or misrepresent your identity, age, or affiliation;
                - Attempt to circumvent moderation, verification, or access controls;
                - Scrape, harvest, or systematically extract data from the Platform without prior written permission;
                - Interfere with the Platform's operation, security, or availability.

                Zero tolerance applies to content or conduct involving minors or human trafficking. The Platform will remove such content immediately upon discovery and cooperate with law enforcement as required by applicable law.

                ## 6. Content and Listings

                Users who publish listings retain ownership of the content they submit, but grant the Platform a non-exclusive, worldwide, royalty-free license to host, display, and distribute that content as necessary to operate the Platform. The Platform may review, moderate, edit the presentation of, suspend, or remove any listing or content at its discretion, including for suspected violations of these Terms, the Provider Policy, or the Media Policy.

                ## 7. Third-Party Interactions

                Any interaction, communication, or arrangement between a visitor and a provider is strictly between those parties. The Platform is not responsible for the accuracy of listings, the conduct of any user, or the outcome of any interaction. Exercise your own judgment and take reasonable precautions.

                ## 8. Disclaimers

                The Platform is provided "as is" and "as available," without warranties of any kind, express or implied, including warranties of merchantability, fitness for a particular purpose, or non-infringement. The Platform does not warrant that listings are accurate, complete, or that any provider is who they claim to be.

                ## 9. Limitation of Liability

                To the maximum extent permitted by law, {{platform_name}} and its owners, operators, and staff will not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or data, arising from your use of the Platform, even if advised of the possibility of such damages.

                ## 10. Indemnification

                You agree to indemnify and hold harmless {{platform_name}} from any claims, damages, losses, or expenses (including reasonable legal fees) arising from your use of the Platform, your content, or your violation of these Terms.

                ## 11. Suspension and Termination

                The Platform may suspend or terminate your access at any time, with or without notice, for conduct it believes violates these Terms, applicable law, or is otherwise harmful to the Platform or other users.

                ## 12. Governing Law

                These Terms are governed by the laws of the jurisdiction in which {{platform_name}} operates, without regard to conflict-of-law principles, except where applicable law requires otherwise.

                ## 13. Changes to these Terms

                The Platform may update these Terms from time to time. Material changes will be published on this page with an updated effective date. Continued use of the Platform after changes take effect constitutes acceptance of the revised Terms.

                ## 14. Contact

                Questions about these Terms can be sent to {{support_email}}.
                MD,
            ],

            'privacy' => [
                'version' => '1.0',
                'title' => 'Privacy Policy',
                'summary' => 'How {{platform_name}} collects, uses, and protects personal data.',
                'requires_reacceptance' => true,
                'content' => <<<'MD'
                ## 1. Overview

                This Privacy Policy explains how {{platform_name}} (the "Platform") collects, uses, discloses, and safeguards information when you visit the website, create an account, or publish a listing.

                ## 2. Information We Collect

                - **Account information**: name, email address, password (stored hashed, never in plain text), and account type.
                - **Listing information**: content you choose to publish, including descriptions, contact methods, and uploaded media.
                - **Verification information**: information submitted to confirm identity or eligibility, handled under stricter access controls than general account data.
                - **Usage data**: pages viewed, search terms, device and browser information, and approximate location inferred from IP address, collected automatically to operate and secure the Platform.
                - **Communications**: information you provide when contacting support or submitting a report.

                ## 3. How We Use Information

                We use collected information to: operate and display listings; authenticate accounts and prevent fraud or abuse; moderate content for compliance with our policies and the law; respond to support requests and reports; analyze and improve the Platform; and comply with legal obligations, including responding to lawful requests from law enforcement.

                ## 4. Cookies and Similar Technologies

                The Platform uses cookies and similar technologies to maintain sessions, remember preferences (including age-verification confirmation, where enabled), and understand aggregate usage. You can control cookies through your browser settings; disabling them may affect functionality.

                ## 5. How We Share Information

                We do not sell personal information. We may share information with: service providers who process data on our behalf (e.g., hosting, email delivery) under confidentiality obligations; law enforcement or regulators where required by law or to protect the safety of any person; and other parties with your consent. Publicly published listing content is, by design, visible to anyone who visits the Platform.

                ## 6. Data Retention

                We retain personal information for as long as necessary to provide the Platform's services, comply with legal obligations, resolve disputes, and enforce our agreements. Deleted accounts are handled according to our retention schedule, which may retain minimal records for a limited period for fraud-prevention, legal, and safety purposes before permanent removal.

                ## 7. Your Rights

                Depending on your jurisdiction, you may have the right to access, correct, delete, or export your personal information, and to object to or restrict certain processing. To exercise these rights, contact {{support_email}}. We may need to verify your identity before acting on a request.

                ## 8. Data Security

                We apply technical and organizational measures designed to protect information against unauthorized access, alteration, disclosure, or destruction. No method of transmission or storage is completely secure, and we cannot guarantee absolute security.

                ## 9. Children's Privacy

                The Platform is restricted to users 18 and older and is not directed at children. We do not knowingly collect information from anyone under 18. If we become aware that we have, we will delete it promptly.

                ## 10. International Data Transfers

                If you access the Platform from outside the country where it is hosted, your information may be transferred to and processed in a different jurisdiction, which may have different data protection laws than your own.

                ## 11. Changes to this Policy

                We may update this Privacy Policy from time to time. Material changes will be published on this page with an updated effective date.

                ## 12. Contact

                Questions about this Privacy Policy, or requests regarding your personal data, can be sent to {{support_email}}.
                MD,
            ],

            'provider' => [
                'version' => '1.0',
                'title' => 'Provider Policy',
                'summary' => 'Requirements and standards for providers listing on {{platform_name}}.',
                'requires_reacceptance' => true,
                'content' => <<<'MD'
                ## 1. Who this Policy Applies To

                This Provider Policy applies to any individual who creates or maintains a listing on {{platform_name}} (the "Platform"), whether as an independent provider or through an agency account, in addition to the general Terms of Use.

                ## 2. Eligibility and Verification

                Providers must be at least 18 years old and must truthfully complete any identity or age verification the Platform requires before or after publication. The Platform may suspend a listing at any time pending verification and may permanently remove any listing where verification cannot be confirmed.

                ## 3. Listing Accuracy

                Providers are responsible for ensuring their listing — including photos, description, location, availability, and rates — is accurate, current, and belongs to them. Listings must not misrepresent age, identity, location, or any material fact.

                ## 4. Content Standards

                Listings are subject to the Media Policy for any uploaded images, and must not contain: content involving anyone under 18; content suggesting trafficking, coercion, or non-consensual activity; contact information that is deceptive or belongs to another person; or claims the Platform reasonably believes to be false or unlawful.

                ## 5. Moderation and Review

                All listings and listing changes may be subject to review before or after publication. The Platform may reject, edit for presentation, suspend, or permanently remove a listing at its discretion, including for suspected violations of this Policy, the Terms of Use, or applicable law. Providers may appeal a moderation decision through the process made available in their account.

                ## 6. Packages and Fees

                Where the Platform offers paid listing packages or promotional placements, the price, duration, and features of each package are as displayed at the time of purchase. Fees are generally non-refundable once a package has been activated, except where required by law or expressly stated otherwise.

                ## 7. Suspension and Termination

                The Platform may suspend or terminate a provider's account or listing at any time for violation of this Policy, the Terms of Use, or applicable law, or where continued listing poses a risk to the Platform, its users, or any third party. Serious violations, including any indication of trafficking or involvement of a minor, will result in immediate removal and may be reported to law enforcement.

                ## 8. Compliance with Law

                Providers are solely responsible for complying with all laws applicable to their activity and jurisdiction. The Platform does not provide legal advice and listing on the Platform does not imply that any particular activity is lawful in a provider's location.

                ## 9. Changes to this Policy

                This Policy may be updated from time to time. Continuing to maintain a listing after changes take effect constitutes acceptance of the revised Policy.

                ## 10. Contact

                Questions about this Provider Policy can be sent to {{support_email}}.
                MD,
            ],

            'media' => [
                'version' => '1.0',
                'title' => 'Media Policy',
                'summary' => 'Standards for images and other media uploaded to {{platform_name}}.',
                'requires_reacceptance' => false,
                'content' => <<<'MD'
                ## 1. Scope

                This Media Policy governs images and other media uploaded to {{platform_name}} (the "Platform") as part of a listing, and applies in addition to the Terms of Use and Provider Policy.

                ## 2. Ownership and License

                You must own the rights to, or have the necessary permissions for, any media you upload. By uploading media, you grant the Platform a non-exclusive, worldwide, royalty-free license to host, resize, convert, and display it as part of your listing for as long as the listing remains published.

                ## 3. Verification and Review

                Uploaded media may be held for automated and/or manual review before publication. The Platform may request a verification photo or additional confirmation that the uploader is the subject of the media and the rightful owner of the account.

                ## 4. Prohibited Content

                Media must not depict or include: anyone under 18 years old, in any context or manner; anyone who has not given informed consent to appear; non-consensual or coerced acts; identifiable third parties who are not part of the listing without their consent; content that violates a person's likeness or publicity rights; or content otherwise unlawful in the jurisdiction the Platform operates from.

                ## 5. Quality and Presentation Standards

                The Platform may set and adjust technical requirements for uploads (such as minimum/maximum resolution, file size, and format) and may decline media that does not meet them, or that is misleading (for example, materially altered to misrepresent appearance).

                ## 6. Rejection and Removal

                The Platform may reject or remove any media at its discretion, including media that violates this Policy, is subject to a valid copyright or rights complaint, or is flagged through the reporting process. Rejected media that is not approved is not published and is handled under the Platform's retention practices.

                ## 7. Copyright Complaints

                If you believe media on the Platform infringes your copyright, contact {{support_email}} with a description of the work, its location on the Platform, and your contact information. The Platform will review and remove infringing content where warranted.

                ## 8. Changes to this Policy

                This Policy may be updated from time to time. Material changes will be published on this page with an updated effective date.

                ## 9. Contact

                Questions about this Media Policy can be sent to {{support_email}}.
                MD,
            ],

            'agency' => [
                'version' => '1.0',
                'title' => 'Agency Policy',
                'summary' => 'Terms for agency accounts representing providers on {{platform_name}}.',
                'requires_reacceptance' => true,
                'content' => <<<'MD'
                ## 1. Scope

                This Agency Policy applies to any account registered on {{platform_name}} (the "Platform") as an agency, representing one or more providers, in addition to the Terms of Use and Provider Policy.

                ## 2. Agency Responsibilities

                An agency account is responsible for the conduct of every listing it manages. Before representing a provider, an agency must confirm that the provider is at least 18 years old, is participating voluntarily and with full knowledge of the listing's content, and consents to the agency managing their listing on the Platform.

                ## 3. Prohibited Practices

                Agencies must not: represent anyone under 18 years old, under any circumstance; coerce, deceive, or financially trap a provider into being listed or remaining listed; withhold a provider's identification, earnings, or ability to leave the arrangement; or misrepresent the nature of the agency-provider relationship to the Platform or to visitors.

                Zero tolerance applies to trafficking or exploitation. Any indication of coercion, deception, or involvement of a minor will result in immediate suspension of the agency account and all listings it manages, and may be reported to law enforcement.

                ## 4. Provider Consent and Removal

                A provider represented by an agency may request removal of their listing at any time. Agencies must honor such a request without delay and must not re-publish a provider's listing after a withdrawal of consent.

                ## 5. Verification

                The Platform may require agencies to provide verification of their business and of each represented provider's identity, age, and consent, at onboarding or at any later time, and may suspend listings pending verification.

                ## 6. Liability

                An agency is responsible for the accuracy of every listing it manages and for ensuring its own compliance, and that of its represented providers, with applicable law. The Platform's relationship is with the agency account; it does not independently vet each represented provider beyond the verification it requests.

                ## 7. Suspension and Termination

                The Platform may suspend or terminate an agency account, and any listings it manages, at any time for violation of this Policy, the Provider Policy, the Terms of Use, or applicable law.

                ## 8. Changes to this Policy

                This Policy may be updated from time to time. Continuing to operate an agency account after changes take effect constitutes acceptance of the revised Policy.

                ## 9. Contact

                Questions about this Agency Policy can be sent to {{support_email}}.
                MD,
            ],
        ];
    }
}
