import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:open_filex/open_filex.dart';

import '../logistics_api.dart';

const Color darkMaroon = Color(0xFF671B1D);
const Color maroon = Color(0xFF873632);
const Color brown = Color(0xFF9E7161);
const Color cream = Color(0xFFFFF8E7);
const Color paleCream = Color(0xFFFFFDF5);
const Color borderColor = Color(0xFFE8D6C3);
const Color successGreen = Color(0xFF427A52);
const Color warningOrange = Color(0xFFB87920);
const Color dangerRed = Color(0xFFB3261E);
const Color infoBlue = Color(0xFF4A7FBF);
const Color mutedColor = Color(0xFF8C7770);
const Color textColor = Color(0xFF382523);

class RiderDetailScreen extends StatefulWidget {
  final Map<String, dynamic> rider;
  final Future<void> Function()? onApprove;
  final Future<void> Function()? onDecline;

  const RiderDetailScreen({
    super.key,
    required this.rider,
    this.onApprove,
    this.onDecline,
  });

  @override
  State<RiderDetailScreen> createState() => _RiderDetailScreenState();
}

class _RiderDetailScreenState extends State<RiderDetailScreen> {
  bool _isProcessing = false;
  String? _activeAction;

  /// Documents the reviewer has opened in this session.
  final Set<String> _reviewed = {};

  static const List<_DocSpec> _docSpecs = [
    _DocSpec(
      key: 'licenseDocument',
      title: "Driver's license",
      icon: Icons.badge_outlined,
      allowed: {'png', 'jpg', 'jpeg', 'pdf', 'doc', 'docx'},
      acceptedLabel: 'PNG, JPG, PDF, DOC or DOCX',
    ),
    _DocSpec(
      key: 'orDocument',
      title: 'Official receipt (OR)',
      icon: Icons.description_outlined,
      allowed: {'pdf', 'doc', 'docx'},
      acceptedLabel: 'PDF, DOC or DOCX',
    ),
    _DocSpec(
      key: 'crDocument',
      title: 'Certificate of registration (CR)',
      icon: Icons.article_outlined,
      allowed: {'pdf', 'doc', 'docx'},
      acceptedLabel: 'PDF, DOC or DOCX',
    ),
  ];

  static const Set<String> _imageExts = {'png', 'jpg', 'jpeg', 'webp'};

  Map<String, dynamic> get rider => widget.rider;

  String get _status => '${rider['status'] ?? 'Unknown'}';

  bool get _isPending =>
      _status == 'Pending Approval' || _status == 'Pending verification';

  bool get _isClosed =>
      _status == 'Declined' ||
      _status == 'Suspended' ||
      _status == 'Terminated';

  bool get _canReview =>
      _isPending && widget.onApprove != null && widget.onDecline != null;

  Color get _statusColor {
    switch (_status) {
      case 'Available':
        return successGreen;
      case 'On Delivery':
        return infoBlue;
      case 'Pending Approval':
      case 'Pending verification':
        return warningOrange;
      case 'Suspended':
      case 'Terminated':
      case 'Declined':
        return dangerRed;
      default:
        return brown;
    }
  }

  /// Other names the same field may have in the rider data (the signup form
  /// saves the vehicle as `vehicleType`, for example).
  static const Map<String, List<String>> _aliases = {
    'vehicle': ['vehicleType'],
    'preferredHub': ['logisticsCenter', 'preferredLogisticsHub'],
  };

  dynamic _lookup(String key) {
    final direct = rider[key];
    if (direct != null && direct.toString().trim().isNotEmpty) return direct;

    for (final alias in _aliases[key] ?? const <String>[]) {
      final value = rider[alias];
      if (value != null && value.toString().trim().isNotEmpty) return value;
    }
    return null;
  }

  bool _has(String key) => _lookup(key) != null;

  String _value(String key, [String fallback = 'Not provided']) {
    final value = _lookup(key);
    return value == null ? fallback : value.toString().trim();
  }

  /// Full address built from the signup address fields when the rider data
  /// has no ready-made `address`.
  String get _composedAddress {
    if (_has('address')) return _value('address');

    return [
      'houseUnit',
      'street',
      'barangay',
      'municipality',
      'province',
      'region',
    ].where(_has).map(_value).join(', ');
  }

  // ---------------------------------------------------------------------------
  // Actions
  // ---------------------------------------------------------------------------

  Future<void> _runAction(
    String actionKey,
    Future<void> Function()? action,
  ) async {
    if (action == null || _isProcessing) return;

    setState(() {
      _isProcessing = true;
      _activeAction = actionKey;
    });

    try {
      await action();
    } catch (_) {
      _toast('Action failed. Please try again.');
    } finally {
      if (mounted) {
        setState(() {
          _isProcessing = false;
          _activeAction = null;
        });
      }
    }
  }

  Future<void> _confirmAndRun({
    required String actionKey,
    required String title,
    required String message,
    required String confirmLabel,
    required Color confirmColor,
    required Future<void> Function()? action,
  }) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: paleCream,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          title,
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        content: Text(
          message,
          style: const TextStyle(color: textColor, fontSize: 14, height: 1.45),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel', style: TextStyle(color: mutedColor)),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: confirmColor,
              foregroundColor: Colors.white,
            ),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await _runAction(actionKey, action);
    }
  }

  Future<void> _copy(String label, String value) async {
    await Clipboard.setData(ClipboardData(text: value));
    _toast('$label copied');
  }

  void _toast(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          behavior: SnackBarBehavior.floating,
          backgroundColor: darkMaroon,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
      );
  }

  // ---------------------------------------------------------------------------
  // Documents: data
  // ---------------------------------------------------------------------------

  String _extensionOf(String value) {
    final clean = value.split('?').first.split('#').first;
    final dot = clean.lastIndexOf('.');
    if (dot == -1 || dot == clean.length - 1) return '';
    return clean.substring(dot + 1).toLowerCase();
  }

  _UploadedDoc? _uploadedDoc(String key) {
    final raw = rider[key];
    if (raw == null) return null;

    String path;
    String name = '';

    if (raw is Map) {
      path = '${raw['path'] ?? raw['url'] ?? ''}'.trim();
      name = '${raw['name'] ?? ''}'.trim();
    } else if (raw is File) {
      path = raw.path;
    } else {
      path = raw.toString().trim();
    }

    if (path.isEmpty) return null;

    if (name.isEmpty) {
      final clean = path.split('?').first.split('#').first;
      name = clean.split(RegExp(r'[\\/]')).last;
    }

    var ext = _extensionOf(name);
    if (ext.isEmpty) ext = _extensionOf(path);

    return _UploadedDoc(path: path, name: name, ext: ext);
  }

  _DocStatus _statusOf(_DocSpec spec, _UploadedDoc? doc) {
    if (doc == null) return _DocStatus.missing;
    if (!spec.allowed.contains(doc.ext)) return _DocStatus.invalid;
    if (_reviewed.contains(spec.key)) return _DocStatus.reviewed;
    return _DocStatus.ready;
  }

  String get _approveWarning {
    final problems = <String>[];
    final unreviewed = <String>[];

    for (final spec in _docSpecs) {
      final status = _statusOf(spec, _uploadedDoc(spec.key));
      if (status == _DocStatus.missing || status == _DocStatus.invalid) {
        problems.add(spec.title);
      } else if (status == _DocStatus.ready) {
        unreviewed.add(spec.title);
      }
    }

    final buffer = StringBuffer();

    if (problems.isNotEmpty) {
      buffer.write(
        '\n\nWarning: ${problems.join(' and ')} '
        '${problems.length == 1 ? 'is' : 'are'} missing or in the wrong format.',
      );
    }

    if (unreviewed.isNotEmpty) {
      buffer.write('\n\nNot reviewed yet: ${unreviewed.join(', ')}.');
    }

    return buffer.toString();
  }

  // ---------------------------------------------------------------------------
  // Documents: review
  // ---------------------------------------------------------------------------

  Future<void> _reviewDocument(_DocSpec spec, _UploadedDoc doc) async {
    try {
      var filePath = doc.path;
      if (filePath.startsWith('/logistics/')) {
        final bytes = await LogisticsApi.downloadRiderDocument(filePath);
        final safeName = '${rider['id']}_${spec.key}_'
            '${DateTime.now().millisecondsSinceEpoch}.${doc.ext}';
        final file = File('${Directory.systemTemp.path}${Platform.pathSeparator}$safeName');
        await file.writeAsBytes(bytes, flush: true);
        filePath = file.path;
      }

      if (_imageExts.contains(doc.ext)) {
        if (!doc.isRemote && !await File(filePath).exists()) {
          _toast('File not found.');
          return;
        }
        if (!mounted) return;

        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => _DocumentImageViewer(
              title: spec.title,
              fileName: doc.name,
              path: filePath,
              isRemote: doc.isRemote,
            ),
          ),
        );
      } else if (!doc.isRemote) {
        if (!await File(filePath).exists()) {
          _toast('File not found.');
          return;
        }

        final result = await OpenFilex.open(filePath);

        if (result.type == ResultType.noAppToOpen) {
          _toast('No app found to open .${doc.ext} files.');
          return;
        }
        if (result.type != ResultType.done) {
          _toast('Could not open this document.');
          return;
        }
      } else {
        _toast('Unable to open this document format.');
        return;
      }

      if (!mounted) return;
      setState(() => _reviewed.add(spec.key));
    } catch (_) {
      _toast('Unable to open this document.');
    }
  }

  // ---------------------------------------------------------------------------
  // Build
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final name = _value('name', 'Rider');

    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: const Text(
          'Rider profile',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
        ),
      ),
      body: ListView(
        physics: const BouncingScrollPhysics(),
        padding: EdgeInsets.zero,
        children: [
          _buildHeader(name),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_isPending) ...[
                  _buildPendingNotice(),
                  const SizedBox(height: 14),
                ],
                if (!_isPending) ...[
                  _buildPerformanceCard(),
                  const SizedBox(height: 14),
                ],
                if (_isClosed) ...[
                  _buildStatusNotice(),
                  const SizedBox(height: 14),
                ],
                _buildSection(
                  title: 'Personal information',
                  icon: Icons.person_outline_rounded,
                  rows: [
                    _field('Full name', 'name'),
                    _field('Rider ID', 'id', copyable: true),
                    _field('Sex', 'sex'),
                    _field('Birthday', 'birthday'),
                    _field('Age', 'age'),
                  ],
                ),
                const SizedBox(height: 14),
                _buildSection(
                  title: 'Contact',
                  icon: Icons.contact_phone_outlined,
                  rows: [
                    _field('Email', 'email', copyable: true),
                    _field('Phone', 'phone', copyable: true),
                  ],
                ),
                const SizedBox(height: 14),
                _buildSection(
                  title: 'Home address',
                  icon: Icons.location_on_outlined,
                  rows: [
                    _field('Region', 'region'),
                    _field('House / Unit', 'houseUnit'),
                    _field('Street', 'street'),
                    _field('Barangay', 'barangay'),
                    _field('Municipality', 'municipality'),
                    _field('Province', 'province'),
                    _infoRow(
                      'Full address',
                      _composedAddress.isEmpty
                          ? 'Not provided'
                          : _composedAddress,
                      isEmpty: _composedAddress.isEmpty,
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _buildSection(
                  title: 'Vehicle',
                  icon: Icons.two_wheeler_outlined,
                  rows: [
                    _field('Type', 'vehicle'),
                    _field('Job type', 'jobType'),
                    _field('Plate number', 'plateNumber', copyable: true),
                    _field('License number', 'licenseNumber', copyable: true),
                  ],
                ),
                const SizedBox(height: 14),
                _buildDocumentsSection(),
                const SizedBox(height: 14),
                _buildSection(
                  title: 'Logistics',
                  icon: Icons.warehouse_outlined,
                  rows: [
                    _field('Preferred hub', 'preferredHub'),
                    _field('Assigned hub', 'hub', fallback: 'Not assigned'),
                    _field('Delivery area', 'area', fallback: 'Not assigned'),
                    _field('Joined', 'joined'),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: _canReview ? _buildReviewBar() : null,
    );
  }

  // ---------------------------------------------------------------------------
  // Header
  // ---------------------------------------------------------------------------

  Widget _buildHeader(String name) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [darkMaroon, maroon],
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Column(
        children: [
          Container(
            width: 88,
            height: 88,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: cream,
              shape: BoxShape.circle,
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.35),
                width: 3,
              ),
            ),
            child: Text(
              _initials(name),
              style: const TextStyle(
                color: darkMaroon,
                fontSize: 28,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            name,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w800,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            _has('vehicle')
                ? '${_value('id')}  •  ${_value('vehicle')}'
                : _value('id'),
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white70, fontSize: 13),
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(30),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: _statusColor,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  _status,
                  style: TextStyle(
                    color: _statusColor,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Notices
  // ---------------------------------------------------------------------------

  Widget _buildPendingNotice() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: warningOrange.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: warningOrange.withValues(alpha: 0.35)),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.pending_actions_rounded, color: warningOrange, size: 22),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Application awaiting review',
                  style: TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.w800,
                    fontSize: 14,
                  ),
                ),
                SizedBox(height: 3),
                Text(
                  'Review the rider details and uploaded documents below.',
                  style: TextStyle(color: textColor, fontSize: 13, height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusNotice() {
    final message = _status == 'Declined'
        ? 'This application was declined. It stays in the application history.'
        : _status == 'Suspended'
        ? 'This rider is suspended and should not receive new assignments.'
        : 'This account is terminated. The record is retained.';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: dangerRed.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: dangerRed.withValues(alpha: 0.25)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline_rounded, color: dangerRed, size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: textColor,
                fontSize: 13,
                height: 1.45,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Performance
  // ---------------------------------------------------------------------------

  Widget _buildPerformanceCard() {
    final delivered = int.tryParse(_value('delivered', '0')) ?? 0;
    final failed = int.tryParse(_value('failed', '0')) ?? 0;
    final ongoing = int.tryParse(_value('ongoing', '0')) ?? 0;
    final finished = delivered + failed;
    final rate = finished == 0 ? null : delivered / finished;

    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionTitle('Delivery performance', Icons.insights_outlined),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _statTile('$delivered', 'Delivered', successGreen),
              ),
              const SizedBox(width: 10),
              Expanded(child: _statTile('$ongoing', 'Ongoing', infoBlue)),
              const SizedBox(width: 10),
              Expanded(child: _statTile('$failed', 'Failed', dangerRed)),
            ],
          ),
          if (rate != null) ...[
            const SizedBox(height: 16),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Success rate',
                    style: TextStyle(color: mutedColor, fontSize: 13),
                  ),
                ),
                Text(
                  '${(rate * 100).round()}%',
                  style: const TextStyle(
                    color: textColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: LinearProgressIndicator(
                value: rate,
                minHeight: 7,
                color: successGreen,
                backgroundColor: dangerRed.withValues(alpha: 0.18),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _statTile(String value, String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              color: color,
              fontSize: 24,
              fontWeight: FontWeight.w800,
              height: 1.1,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: textColor,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Cards, sections and rows
  // ---------------------------------------------------------------------------

  Widget _card({required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );
  }

  Widget _sectionTitle(String title, IconData icon) {
    return Row(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: darkMaroon.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(9),
          ),
          child: Icon(icon, color: darkMaroon, size: 18),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSection({
    required String title,
    required IconData icon,
    required List<Widget> rows,
  }) {
    final spaced = <Widget>[];
    for (var i = 0; i < rows.length; i++) {
      spaced.add(rows[i]);
      if (i < rows.length - 1) {
        spaced.add(const Divider(height: 1, thickness: 1, color: borderColor));
      }
    }

    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionTitle(title, icon),
          const SizedBox(height: 10),
          ...spaced,
        ],
      ),
    );
  }

  Widget _field(
    String label,
    String key, {
    String fallback = 'Not provided',
    bool copyable = false,
  }) {
    final hasValue = _has(key);
    return _infoRow(
      label,
      hasValue ? _value(key) : fallback,
      isEmpty: !hasValue,
      copyable: copyable && hasValue,
    );
  }

  Widget _infoRow(
    String label,
    String value, {
    bool isEmpty = false,
    bool copyable = false,
  }) {
    final content = Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 4,
            child: Text(
              label,
              style: const TextStyle(
                color: mutedColor,
                fontSize: 13,
                height: 1.3,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 6,
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(
                color: isEmpty ? mutedColor : textColor,
                fontSize: 14,
                height: 1.3,
                fontWeight: isEmpty ? FontWeight.w400 : FontWeight.w600,
                fontStyle: isEmpty ? FontStyle.italic : FontStyle.normal,
              ),
            ),
          ),
          if (copyable) ...[
            const SizedBox(width: 8),
            const Padding(
              padding: EdgeInsets.only(top: 1),
              child: Icon(Icons.copy_rounded, size: 15, color: brown),
            ),
          ],
        ],
      ),
    );

    if (!copyable) return content;

    return InkWell(onTap: () => _copy(label, value), child: content);
  }

  // ---------------------------------------------------------------------------
  // Submitted documents
  // ---------------------------------------------------------------------------

  Widget _buildDocumentsSection() {
    final reviewedCount = _docSpecs
        .where((spec) => _reviewed.contains(spec.key))
        .length;
    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionTitle('Submitted documents', Icons.folder_open_outlined),
          const SizedBox(height: 6),
          Text(
            '$reviewedCount of ${_docSpecs.length} reviewed',
            style: const TextStyle(color: mutedColor, fontSize: 12),
          ),
          const SizedBox(height: 14),
          for (var i = 0; i < _docSpecs.length; i++) ...[
            if (i > 0) const SizedBox(height: 12),
            _documentTile(_docSpecs[i]),
          ],
        ],
      ),
    );
  }

  Widget _documentTile(_DocSpec spec) {
    final doc = _uploadedDoc(spec.key);
    final status = _statusOf(spec, doc);

    final (String label, Color color) = switch (status) {
      _DocStatus.missing => ('Not submitted', mutedColor),
      _DocStatus.invalid => ('Wrong format', dangerRed),
      _DocStatus.ready => ('Ready to review', warningOrange),
      _DocStatus.reviewed => ('Reviewed', successGreen),
    };

    final IconData viewIcon = doc == null
        ? Icons.visibility_outlined
        : _imageExts.contains(doc.ext)
        ? Icons.visibility_outlined
        : doc.ext == 'pdf'
        ? Icons.picture_as_pdf_outlined
        : Icons.description_outlined;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: paleCream,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: borderColor),
                ),
                child: Icon(spec.icon, color: darkMaroon, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      spec.title,
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      doc?.name ?? 'No file submitted',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: mutedColor, fontSize: 12),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  label,
                  style: TextStyle(
                    color: color,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            status == _DocStatus.missing
                ? 'The rider has not submitted this document.'
                : status == _DocStatus.invalid
                ? 'Expected ${spec.acceptedLabel}, but this file is '
                      '.${doc!.ext.isEmpty ? 'unknown' : doc.ext}.'
                : 'Accepted format: ${spec.acceptedLabel}',
            style: TextStyle(
              color: status == _DocStatus.invalid ? dangerRed : mutedColor,
              fontSize: 12,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: doc == null ? null : () => _reviewDocument(spec, doc),
              icon: Icon(viewIcon, size: 18),
              label: Text(
                status == _DocStatus.reviewed ? 'View again' : 'View file',
              ),
              style: FilledButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
                disabledBackgroundColor: borderColor,
                disabledForegroundColor: mutedColor,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(11),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Review bar (pinned)
  // ---------------------------------------------------------------------------

  Widget _buildReviewBar() {
    final approving = _isProcessing && _activeAction == 'approve';
    final declining = _isProcessing && _activeAction == 'decline';

    return Container(
      decoration: BoxDecoration(
        color: paleCream,
        border: const Border(top: BorderSide(color: borderColor)),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Review the submitted documents before approving.',
                textAlign: TextAlign.center,
                style: TextStyle(color: mutedColor, fontSize: 12),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _isProcessing
                          ? null
                          : () => _confirmAndRun(
                              actionKey: 'decline',
                              title: 'Decline this application?',
                              message:
                                  '${_value('name', 'This applicant')} will be moved to the application history and will not become a registered rider.',
                              confirmLabel: 'Decline',
                              confirmColor: dangerRed,
                              action: widget.onDecline,
                            ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: dangerRed,
                        side: BorderSide(
                          color: _isProcessing
                              ? borderColor
                              : dangerRed.withValues(alpha: 0.7),
                        ),
                        minimumSize: const Size.fromHeight(50),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: declining
                          ? _spinner(dangerRed)
                          : const Text(
                              'Decline',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: FilledButton.icon(
                      onPressed: _isProcessing
                          ? null
                          : () => _confirmAndRun(
                              actionKey: 'approve',
                              title: 'Approve this rider?',
                              message:
                                  '${_value('name', 'This applicant')} will become a registered rider and can receive delivery assignments.$_approveWarning',
                              confirmLabel: 'Approve',
                              confirmColor: successGreen,
                              action: widget.onApprove,
                            ),
                      icon: approving
                          ? _spinner(Colors.white)
                          : const Icon(Icons.check_rounded, size: 20),
                      label: Text(
                        approving ? 'Approving...' : 'Approve rider',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      style: FilledButton.styleFrom(
                        backgroundColor: successGreen,
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: successGreen.withValues(
                          alpha: approving ? 0.75 : 0.35,
                        ),
                        disabledForegroundColor: Colors.white,
                        minimumSize: const Size.fromHeight(50),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _spinner(Color color) {
    return SizedBox(
      width: 18,
      height: 18,
      child: CircularProgressIndicator(strokeWidth: 2.2, color: color),
    );
  }

  String _initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();

    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }
}

// -----------------------------------------------------------------------------
// Document models
// -----------------------------------------------------------------------------

enum _DocStatus { missing, invalid, ready, reviewed }

class _DocSpec {
  final String key;
  final String title;
  final IconData icon;
  final Set<String> allowed;
  final String acceptedLabel;

  const _DocSpec({
    required this.key,
    required this.title,
    required this.icon,
    required this.allowed,
    required this.acceptedLabel,
  });
}

class _UploadedDoc {
  final String path;
  final String name;
  final String ext;

  const _UploadedDoc({
    required this.path,
    required this.name,
    required this.ext,
  });

  bool get isRemote =>
      path.startsWith('http://') || path.startsWith('https://');
}

// -----------------------------------------------------------------------------
// Image viewer (local file or web URL)
// -----------------------------------------------------------------------------

class _DocumentImageViewer extends StatelessWidget {
  final String title;
  final String fileName;
  final String path;
  final bool isRemote;

  const _DocumentImageViewer({
    required this.title,
    required this.fileName,
    required this.path,
    required this.isRemote,
  });

  Widget _error() {
    return const Padding(
      padding: EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.broken_image_outlined, color: Colors.white70, size: 48),
          SizedBox(height: 12),
          Text(
            'Unable to display this image.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.white),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF1E1413),
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            Text(
              fileName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 12, color: Colors.white70),
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: InteractiveViewer(
          minScale: 0.8,
          maxScale: 5,
          child: Center(
            child: isRemote
                ? Image.network(
                    path,
                    fit: BoxFit.contain,
                    loadingBuilder: (context, child, progress) {
                      if (progress == null) return child;
                      return const Center(
                        child: CircularProgressIndicator(color: Colors.white),
                      );
                    },
                    errorBuilder: (context, error, stack) => _error(),
                  )
                : Image.file(
                    File(path),
                    fit: BoxFit.contain,
                    errorBuilder: (context, error, stack) => _error(),
                  ),
          ),
        ),
      ),
    );
  }
}
