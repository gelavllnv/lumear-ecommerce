import 'package:flutter/material.dart';

class TermsConditionsScreen extends StatelessWidget {
  const TermsConditionsScreen({super.key});

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFE0CFC2);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,

      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Terms & Conditions',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),

      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            const Text(
              'Lumear Rider Terms & Conditions',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),

            const SizedBox(height: 8),

            const Text(
              'These terms apply to your Lumear Rider account and the pickup, delivery, and parcel handoff services arranged through the application. Please read them before registering or accepting an assignment.',
              style: TextStyle(fontSize: 14, color: brownBeige, height: 1.5),
            ),

            const SizedBox(height: 25),

            _buildSection(
              title: '1. Parties and Agreement',
              content: 'This agreement is between you and Lumear ("Lumear"), the operator of the Lumear Rider application. By checking "I agree" during registration and submitting your application, or by continuing to use an approved rider account, you agree to these terms and any separate written rider, safety, or payout policies that apply. If a signed agreement conflicts with these terms, the signed agreement controls to the extent permitted by law.',
            ),

            _buildSection(
              title: '2. Eligibility and Registration',
              content: 'You must be legally eligible to perform rider services and hold the licenses, permits, and vehicle documents required where you operate. Information and documents submitted for registration must be accurate, complete, current, and yours to provide. Update your account when relevant details change. Lumear may verify submitted information and may decline or pause an account if required information cannot be verified.',
            ),

            _buildSection(
              title: '3. Account Security',
              content: 'Your rider account is personal to you. Do not share your password, let another person use your account, or create an account using someone else\'s information. You are responsible for activity performed through your account. Report suspected unauthorized access or lost credentials promptly through in-app Help & Support.',
            ),

            _buildSection(
              title: '4. Assignments and Service Standards',
              content: 'Review assignment details and follow the application\'s required pickup, waybill scanning, delivery, status-update, and handoff steps. Keep all records accurate and do not mark a pickup or delivery complete before it occurs. If an instruction is unclear, a recipient cannot be reached, or an assignment cannot be completed safely, contact support and follow the current operating instructions.',
            ),

            _buildSection(
              title: '5. Safety and Legal Compliance',
              content: 'Follow applicable traffic, vehicle, licensing, and safety laws. Do not use the application in a way that distracts you while driving. Treat customers, sellers, other riders, and support staff respectfully. Do not harass, threaten, discriminate against, or abuse anyone, and do not falsify identity, location, scans, delivery status, or other service records.',
            ),

            _buildSection(
              title: '6. Parcel Care and Proof of Service',
              content: 'Take reasonable care of every parcel while it is in your possession and complete the required verification and handoff steps. Do not open, keep, abandon, redirect, or give a parcel to an unauthorized person. Promptly report a missing, damaged, refused, or unsafe parcel. Provide truthful proof of pickup or delivery when required, and capture only information needed to document the service.',
            ),

            _buildSection(
              title: '7. Cash on Delivery and Payouts',
              content: 'For a cash-on-delivery assignment, collect only the amount displayed for that order, record it accurately, keep the funds secure, and remit or reconcile them according to Lumear\'s written instructions. Do not collect an unapproved amount or use collected funds for personal purposes. Report collection or reconciliation discrepancies promptly. Rider fees, payout timing, adjustments, and any permitted deductions must be stated in the applicable written payout policy or rider agreement.',
            ),

            _buildSection(
              title: '8. Personal Information',
              content: 'Use customer and seller names, phone numbers, addresses, parcel details, and delivery evidence only to complete assigned services. Keep that information confidential; do not copy, publish, sell, disclose, or retain it for personal use. Registration information and documents, service records, and any proof images will be handled as described in Lumear\'s Privacy Notice and applicable data-protection law. Privacy Notice: [add an in-app link or URL before publication].',
            ),

            _buildSection(
              title: '9. Location and Device Permissions',
              content: 'When you use a feature that requests location permission, the application may use your device location to support pickup or delivery coordination and display your position in relation to an assignment. Location availability and accuracy depend on your device, network, and permission settings. Do not falsify location information. The application is not a substitute for safe judgment, road signs, or official instructions.',
            ),

            _buildSection(
              title: '10. Fees, Expenses, and Work Arrangement',
              content: 'Any rider fee, reimbursement, expense, or payout arrangement is governed by the applicable written rider agreement or payout policy. These terms do not promise a minimum number of assignments, earnings, or work hours. The nature of the relationship between you and Lumear, and each party\'s legal obligations, are determined by the applicable agreement and law, not by a label in these terms.',
            ),

            _buildSection(
              title: '11. Acceptable Use',
              content: 'Use the application only for authorized Lumear services. Do not interfere with its operation, bypass access controls, misuse another person\'s information, upload harmful content, commit fraud, or use the application to violate a law or another person\'s rights. Do not carry goods prohibited by law or by a written Lumear policy.',
            ),

            _buildSection(
              title: '12. Reviews, Suspension, and Ending Use',
              content: 'Lumear may review reported incidents and restrict or suspend account access when reasonably necessary to address a safety risk, suspected fraud, account security issue, legal obligation, or material breach of these terms. Where reasonably practicable and legally permitted, Lumear will provide the reason and a way to request review through in-app Help & Support. You may stop using the application, but must safely complete or hand off parcels in your custody and reconcile outstanding collected funds under the applicable written instructions.',
            ),

            _buildSection(
              title: '13. Application Availability',
              content: 'The application, maps, GPS, network, and related services may be unavailable, delayed, or inaccurate. Lumear may maintain, change, or discontinue features. Lumear does not guarantee uninterrupted access or a particular number or outcome of assignments. The application and its content remain the property of Lumear or its licensors; you may use them only for authorized rider services.',
            ),

            _buildSection(
              title: '14. Responsibility and Legal Rights',
              content: 'You remain responsible for your own unlawful conduct and intentional misconduct, subject to applicable law. Nothing in these terms excludes or limits a right, remedy, or liability that cannot legally be excluded or limited. Any allocation of responsibility in another written agreement is also subject to applicable law.',
            ),

            _buildSection(
              title: '15. Governing Law and Disputes',
              content: 'Governing law: [insert the jurisdiction confirmed by Lumear]. For a concern, first contact Lumear using the support details below so it can be reviewed. Any unresolved dispute will be handled under the confirmed governing law and any mandatory dispute process, without limiting rights that cannot legally be waived.',
            ),

            _buildSection(
              title: '16. Changes and Contact',
              content: 'Lumear may revise these terms to reflect changes in the application, services, or legal requirements. The current version and effective date will be shown in the application. If the law requires renewed consent for a material change, Lumear will request it before you continue using the affected services. Operator: [insert legal entity name and business address]. Support: [insert monitored support email, phone number, or in-app contact route]. Effective date: [insert date].',
            ),

            const SizedBox(height: 10),

            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),

              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(15),
              ),

              child: const Text(
                'By checking “I agree” and submitting your registration, you confirm that you have read and accepted these Terms & Conditions. If you do not agree, do not submit your registration or use a rider account.',
                style: TextStyle(
                  fontSize: 13,
                  color: darkMaroon,
                  fontWeight: FontWeight.w600,
                  height: 1.5,
                ),
              ),
            ),

            const SizedBox(height: 25),
          ],
        ),
      ),
    );
  }

  Widget _buildSection({required String title, required String content}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 22),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.bold,
              color: maroon,
            ),
          ),

          const SizedBox(height: 7),

          Text(
            content,
            style: const TextStyle(
              fontSize: 14,
              color: Colors.black87,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }
}
