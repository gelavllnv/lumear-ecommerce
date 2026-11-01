import 'package:flutter/material.dart';

class RiderPerformanceScreen extends StatelessWidget {
  const RiderPerformanceScreen({super.key});

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        elevation: 0,
        iconTheme: const IconThemeData(color: darkMaroon),
        title: const Text(
          'Rider Performance',
          style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // OVERALL RATING
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: darkMaroon,
                borderRadius: BorderRadius.circular(24),
              ),
              child: const Column(
                children: [
                  Icon(Icons.star, color: Colors.amber, size: 45),
                  SizedBox(height: 8),
                  Text(
                    '4.8',
                    style: TextStyle(
                      fontSize: 38,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  Text('Overall Rating', style: TextStyle(color: lightBeige)),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // SUMMARY
            const Text(
              'Performance Summary',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),

            const SizedBox(height: 12),

            Row(
              children: [
                Expanded(
                  child: _statCard(
                    icon: Icons.inventory_2_outlined,
                    value: '256',
                    label: 'Deliveries',
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _statCard(
                    icon: Icons.check_circle_outline,
                    value: '98%',
                    label: 'Success Rate',
                  ),
                ),
              ],
            ),

            const SizedBox(height: 10),

            Row(
              children: [
                Expanded(
                  child: _statCard(
                    icon: Icons.timer_outlined,
                    value: '95%',
                    label: 'On Time',
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _statCard(
                    icon: Icons.thumb_up_alt_outlined,
                    value: '97%',
                    label: 'Positive',
                  ),
                ),
              ],
            ),

            const SizedBox(height: 25),

            const Text(
              'Delivery Performance',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),

            const SizedBox(height: 15),

            _performanceItem(
              title: 'Successful Deliveries',
              value: 0.98,
              percentage: '98%',
            ),

            _performanceItem(
              title: 'On-Time Deliveries',
              value: 0.95,
              percentage: '95%',
            ),

            _performanceItem(
              title: 'Customer Satisfaction',
              value: 0.97,
              percentage: '97%',
            ),

            _performanceItem(
              title: 'Parcel Handling',
              value: 0.96,
              percentage: '96%',
            ),

            const SizedBox(height: 25),

            const Text(
              'Rating Breakdown',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),

            const SizedBox(height: 15),

            _ratingRow(stars: 5, value: 0.80, percentage: '80%'),

            _ratingRow(stars: 4, value: 0.15, percentage: '15%'),

            _ratingRow(stars: 3, value: 0.04, percentage: '4%'),

            _ratingRow(stars: 2, value: 0.01, percentage: '1%'),

            _ratingRow(stars: 1, value: 0.00, percentage: '0%'),

            const SizedBox(height: 25),

            const Text(
              'Recent Feedback',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),

            const SizedBox(height: 12),

            _feedbackCard(
              name: 'Maria R.',
              feedback: 'Parcel arrived safely and on time.',
              rating: 5,
            ),

            const SizedBox(height: 10),

            _feedbackCard(
              name: 'John S.',
              feedback: 'Good service and careful parcel handling.',
              rating: 5,
            ),

            const SizedBox(height: 25),

            // TIP
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: lightBeige,
                borderRadius: BorderRadius.circular(18),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.lightbulb_outline, color: darkMaroon),
                  SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Performance Tip',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: darkMaroon,
                          ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Deliver parcels safely, communicate with customers, and complete deliveries on time.',
                          style: TextStyle(fontSize: 12, color: maroon),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _statCard({
    required IconData icon,
    required String value,
    required String label,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Icon(icon, color: darkMaroon, size: 27),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: darkMaroon,
            ),
          ),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 11, color: brownBeige),
          ),
        ],
      ),
    );
  }

  Widget _performanceItem({
    required String title,
    required double value,
    required String percentage,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: darkMaroon,
                ),
              ),
              Text(
                percentage,
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  color: darkMaroon,
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: LinearProgressIndicator(
              value: value,
              minHeight: 9,
              backgroundColor: lightBeige,
              valueColor: const AlwaysStoppedAnimation<Color>(darkMaroon),
            ),
          ),
        ],
      ),
    );
  }

  Widget _ratingRow({
    required int stars,
    required double value,
    required String percentage,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          SizedBox(
            width: 35,
            child: Text(
              '$stars ★',
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: LinearProgressIndicator(
                value: value,
                minHeight: 8,
                backgroundColor: lightBeige,
                valueColor: const AlwaysStoppedAnimation<Color>(darkMaroon),
              ),
            ),
          ),
          const SizedBox(width: 10),
          SizedBox(
            width: 35,
            child: Text(
              percentage,
              style: const TextStyle(fontSize: 11, color: brownBeige),
            ),
          ),
        ],
      ),
    );
  }

  Widget _feedbackCard({
    required String name,
    required String feedback,
    required int rating,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const CircleAvatar(
            backgroundColor: lightBeige,
            child: Icon(Icons.person, color: darkMaroon),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        name,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: darkMaroon,
                        ),
                      ),
                    ),
                    Row(
                      children: List.generate(
                        rating,
                        (index) => const Icon(
                          Icons.star,
                          color: Colors.amber,
                          size: 15,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  feedback,
                  style: const TextStyle(fontSize: 12, color: brownBeige),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
