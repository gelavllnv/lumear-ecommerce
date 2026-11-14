import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_application_1/services/rider_api_service.dart';

import 'bug_report_screen.dart';

class HelpSupportScreen extends StatefulWidget {
  const HelpSupportScreen({super.key});

  @override
  State<HelpSupportScreen> createState() => _HelpSupportScreenState();
}

class _HelpSupportScreenState extends State<HelpSupportScreen> {
  // ============================================================
  // COLORS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);

  static const Color maroon = Color(0xFF873632);

  static const Color brownBeige = Color(0xFF9E7161);

  static const Color lightBeige = Color(0xFFCAAE9F);

  static const Color cream = Color(0xFFE0CFC2);

  static const Color chatBackground = Color(0xFFF8F5F3);

  static const Color inputBackground = Color(0xFFF7F3F1);

  static const Color borderColor = Color(0xFFEAE0DC);

  static const Color darkText = Color(0xFF3F2927);

  static const Color mutedText = Color(0xFFAE9790);

  // ============================================================
  // CONTROLLERS
  // ============================================================

  final TextEditingController _messageController = TextEditingController();

  final ScrollController _scrollController = ScrollController();

  final ImagePicker _imagePicker = ImagePicker();

  // ============================================================
  // STATE
  // ============================================================

  bool _isBotTyping = false;

  String _currentMenu = 'main';

  // Pending attachment means:
  // selected but NOT sent yet.
  String? _pendingAttachmentPath;
  String? _pendingAttachmentType;
  String? _pendingAttachmentName;

  final List<Map<String, dynamic>> _messages = [];

  // ============================================================
  // INIT
  // ============================================================

  @override
  void initState() {
    super.initState();

    _messages.add({
      'message':
          'Hello, Rider! 👋\n\n'
          'Welcome to Lumear AI Support. '
          'How can I help you today?',
      'isRider': false,
      'time': DateTime.now(),
      'sendToGemini': false,
      'attachmentPath': null,
      'attachmentType': null,
      'fileName': null,
    });
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();

    super.dispose();
  }

  // ============================================================
  // SEND
  // ============================================================

  Future<void> _sendMessage() async {
    if (_isBotTyping) {
      return;
    }

    final String text = _messageController.text.trim();

    final bool hasAttachment = _pendingAttachmentPath != null;

    if (text.isEmpty && !hasAttachment) {
      return;
    }

    FocusScope.of(context).unfocus();

    final String? attachmentPath = _pendingAttachmentPath;

    final String? attachmentType = _pendingAttachmentType;

    final String? attachmentName = _pendingAttachmentName;

    _messageController.clear();

    // Remove preview after Send is pressed.
    setState(() {
      _pendingAttachmentPath = null;
      _pendingAttachmentType = null;
      _pendingAttachmentName = null;
    });

    await _sendToLaravelAi(
      riderMessage: text,
      attachmentPath: attachmentPath,
      attachmentType: attachmentType,
      attachmentName: attachmentName,
    );
  }

  // ============================================================
  // ADD RIDER MESSAGE
  // ============================================================

  void _addRiderMessage(
    String message, {
    bool sendToGemini = true,
    String? attachmentPath,
    String? attachmentType,
    String? fileName,
  }) {
    if (!mounted) {
      return;
    }

    setState(() {
      _messages.add({
        'message': message,
        'isRider': true,
        'time': DateTime.now(),
        'sendToGemini': sendToGemini,
        'attachmentPath': attachmentPath,
        'attachmentType': attachmentType,
        'fileName': fileName,
      });
    });

    _scrollToBottom();
  }

  // ============================================================
  // LOCAL AI MESSAGE
  // ============================================================

  Future<void> _addLocalBotMessage(String message) async {
    if (!mounted) {
      return;
    }

    setState(() {
      _isBotTyping = true;
    });

    _scrollToBottom();

    await Future.delayed(const Duration(milliseconds: 350));

    if (!mounted) {
      return;
    }

    setState(() {
      _messages.add({
        'message': message,
        'isRider': false,
        'time': DateTime.now(),
        'sendToGemini': false,
        'attachmentPath': null,
        'attachmentType': null,
        'fileName': null,
      });

      _isBotTyping = false;
    });

    _scrollToBottom();
  }

  // ============================================================
  // LUMEAR AI BACKEND
  // ============================================================

  Future<void> _sendToLaravelAi({
    required String riderMessage,
    String? attachmentPath,
    String? attachmentType,
    String? attachmentName,
  }) async {
    final String displayMessage = riderMessage.isNotEmpty
        ? riderMessage
        : _defaultAttachmentPrompt(attachmentType);

    _addRiderMessage(
      displayMessage,
      sendToGemini: true,
      attachmentPath: attachmentPath,
      attachmentType: attachmentType,
      fileName: attachmentName,
    );
    if (!mounted) return;

    setState(() => _isBotTyping = true);
    _scrollToBottom();

    try {
      if (attachmentPath != null) {
        final File attachment = File(attachmentPath);
        if (!await attachment.exists()) {
          throw Exception('The selected attachment could not be found.');
        }
        if (await attachment.length() > 20 * 1024 * 1024) {
          throw Exception('The selected attachment is larger than 20 MB.');
        }
      }

      final List<Map<String, dynamic>> historyMessages = _messages
          .where(
            (message) =>
                message['sendToGemini'] == true &&
                message['attachmentPath'] == null,
          )
          .toList();
      if (historyMessages.isNotEmpty &&
          historyMessages.last['isRider'] == true &&
          historyMessages.last['message']?.toString() == displayMessage) {
        historyMessages.removeLast();
      }
      final int historyStart = historyMessages.length > 6
          ? historyMessages.length - 6
          : 0;
      final List<Map<String, String>> history = historyMessages
          .skip(historyStart)
          .map(
            (message) => <String, String>{
              'role': message['isRider'] == true ? 'user' : 'assistant',
              'content': message['message']?.toString() ?? '',
            },
          )
          .toList();

      final String answer = await RiderApiService.lumearAiChat(
        question: displayMessage,
        history: history,
        attachmentPath: attachmentPath,
      );
      if (!mounted) return;

      setState(() {
        _messages.add({
          'message': answer,
          'isRider': false,
          'time': DateTime.now(),
          'sendToGemini': true,
          'attachmentPath': null,
          'attachmentType': null,
          'fileName': null,
        });
        _currentMenu = 'answer';
      });
      _scrollToBottom();
    } catch (error) {
      if (!mounted) return;
      _addApiErrorMessage(
        error.toString().contains('larger than 20 MB')
            ? 'The selected attachment is too large. Please choose a file smaller than 20 MB.'
            : error.toString(),
      );
    } finally {
      if (mounted) setState(() => _isBotTyping = false);
    }
  }

  // ============================================================
  // DEFAULT ATTACHMENT PROMPT
  // ============================================================

  String _defaultAttachmentPrompt(String? type) {
    if (type == 'image') {
      return 'Please analyze this image and '
          'help me based on the Lumear Rider App.';
    }

    if (type == 'pdf') {
      return 'Please read this PDF and help me '
          'with any relevant delivery information.';
    }

    if (type == 'text') {
      return 'Please read this document and help '
          'me understand the relevant information.';
    }

    return 'Please analyze this attachment.';
  }

  // ============================================================
  // API ERROR
  // ============================================================

  void _addApiErrorMessage(String message) {
    if (!mounted) {
      return;
    }

    setState(() {
      _messages.add({
        'message': message,
        'isRider': false,
        'time': DateTime.now(),
        'sendToGemini': false,
        'attachmentPath': null,
        'attachmentType': null,
        'fileName': null,
      });

      _isBotTyping = false;
      _currentMenu = 'error';
    });

    _scrollToBottom();
  }

  // ============================================================
  // QUICK REPLIES
  // ============================================================

  List<String> _getQuickReplies() {
    switch (_currentMenu) {
      case 'main':
        return [
          '📦 Delivery Problem',
          '📱 App Problem',
          '📷 QR Scanner',
          '👤 Account Problem',
          '💰 Earnings / Payment',
          '📍 Pickup / Location',
          '🐞 Report a Bug',
        ];

      case 'delivery':
        return [
          'Customer unavailable',
          'Wrong delivery address',
          'Parcel appears damaged',
          'Wrong parcel',
          'Cannot complete delivery',
          '🏠 Back to Main Menu',
        ];

      case 'app':
        return [
          'Start Delivery is missing',
          'Delivery status is not updating',
          'Proof of delivery is not uploading',
          'App is not responding',
          '🏠 Back to Main Menu',
        ];

      case 'qr':
        return [
          'QR code is not scanning',
          'Camera is not opening',
          'Wrong camera is opening',
          'QR verification failed',
          '🏠 Back to Main Menu',
        ];

      case 'account':
        return [
          'Cannot login',
          'Forgot password',
          'Account verification',
          'Update rider profile',
          '🏠 Back to Main Menu',
        ];

      case 'payment':
        return [
          'Missing earnings',
          'Incorrect earnings',
          'Completed delivery not reflected',
          '🏠 Back to Main Menu',
        ];

      case 'location':
        return [
          'Cannot find pickup location',
          'GPS / Map problem',
          'Cannot find customer address',
          '🏠 Back to Main Menu',
        ];

      default:
        return ['🐞 Report a Bug', '🏠 Back to Main Menu'];
    }
  }

  Future<void> _selectQuickReply(String option) async {
    if (_isBotTyping) {
      return;
    }

    if (option == '📦 Delivery Problem') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'delivery';
      });

      await _addLocalBotMessage('What delivery problem are you experiencing?');

      return;
    }

    if (option == '📱 App Problem') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'app';
      });

      await _addLocalBotMessage(
        'What part of the Lumear Rider App '
        'is giving you a problem?',
      );

      return;
    }

    if (option == '📷 QR Scanner') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'qr';
      });

      await _addLocalBotMessage(
        'What problem are you experiencing '
        'with the QR scanner?',
      );

      return;
    }

    if (option == '👤 Account Problem') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'account';
      });

      await _addLocalBotMessage('What account problem are you experiencing?');

      return;
    }

    if (option == '💰 Earnings / Payment') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'payment';
      });

      await _addLocalBotMessage(
        'What earnings or payment concern '
        'are you experiencing?',
      );

      return;
    }

    if (option == '📍 Pickup / Location') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'location';
      });

      await _addLocalBotMessage(
        'What pickup or location concern '
        'are you experiencing?',
      );

      return;
    }
    if (option == '🐞 Report a Bug') {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) =>
              const BugReportScreen(affectedScreen: 'Help & Support'),
        ),
      );

      return;
    }

    if (option == '🏠 Back to Main Menu') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'main';
      });

      await _addLocalBotMessage('Sure! What else can I help you with?');

      return;
    }

    if (option == '🐞 Report a Bug') {
      _addRiderMessage(option, sendToGemini: false);

      await _addLocalBotMessage(
        'Bug reporting will open a form where your rider information, date, and time are added automatically. You will only need to provide screenshots or photos and describe what happened.',
      );

      return;
    }

    await _sendToLaravelAi(riderMessage: option);
  }

  // ============================================================
  // ATTACHMENT MENU
  // ============================================================

  void _showAttachmentMenu() {
    if (_isBotTyping) {
      return;
    }

    FocusScope.of(context).unfocus();

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Container(
            margin: const EdgeInsets.all(12),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(28),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),

                const SizedBox(height: 20),

                const Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Send attachment',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: darkMaroon,
                    ),
                  ),
                ),

                const SizedBox(height: 18),

                Row(
                  children: [
                    Expanded(
                      child: _attachmentOption(
                        icon: Icons.camera_alt_rounded,
                        label: 'Camera',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _pickImage(ImageSource.camera);
                        },
                      ),
                    ),

                    Expanded(
                      child: _attachmentOption(
                        icon: Icons.photo_library_rounded,
                        label: 'Gallery',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _pickImage(ImageSource.gallery);
                        },
                      ),
                    ),

                    Expanded(
                      child: _attachmentOption(
                        icon: Icons.description_rounded,
                        label: 'File',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _pickDocument();
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _attachmentOption({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.all(7),
        child: Column(
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                color: cream,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Icon(icon, color: darkMaroon, size: 27),
            ),

            const SizedBox(height: 8),

            Text(
              label,
              style: const TextStyle(
                color: darkMaroon,
                fontWeight: FontWeight.w600,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // PICK IMAGE
  // ============================================================

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: source,
        imageQuality: 80,
        maxWidth: 1600,
      );

      if (image == null || !mounted) {
        return;
      }

      setState(() {
        _pendingAttachmentPath = image.path;

        _pendingAttachmentType = 'image';

        _pendingAttachmentName = image.name;
      });
    } catch (error) {
      debugPrint('Image picker: $error');

      _showSnackBar('Unable to open the camera or gallery.');
    }
  }

  // ============================================================
  // PICK FILE - FILE_PICKER V12
  // ============================================================

  Future<void> _pickDocument() async {
    try {
      final PlatformFile? file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'txt'],
      );

      if (file == null || !mounted) {
        return;
      }

      if (file.path == null) {
        _showSnackBar('Unable to access the selected file.');

        return;
      }

      final String extension = file.name.split('.').last.toLowerCase();

      String attachmentType;

      if (extension == 'pdf') {
        attachmentType = 'pdf';
      } else {
        attachmentType = 'text';
      }

      setState(() {
        _pendingAttachmentPath = file.path;

        _pendingAttachmentType = attachmentType;

        _pendingAttachmentName = file.name;
      });
    } catch (error) {
      debugPrint('File picker: $error');

      _showSnackBar('Unable to open the file picker.');
    }
  }

  // ============================================================
  // REMOVE ATTACHMENT
  // ============================================================

  void _removePendingAttachment() {
    setState(() {
      _pendingAttachmentPath = null;
      _pendingAttachmentType = null;
      _pendingAttachmentName = null;
    });
  }

  // ============================================================
  // RESET
  // ============================================================

  void _resetConversation() {
    if (_isBotTyping) {
      return;
    }

    setState(() {
      _messages.clear();

      _currentMenu = 'main';

      _pendingAttachmentPath = null;
      _pendingAttachmentType = null;
      _pendingAttachmentName = null;

      _messages.add({
        'message':
            'Hello, Rider! 👋\n\n'
            'Welcome to Lumear AI Support. '
            'How can I help you today?',
        'isRider': false,
        'time': DateTime.now(),
        'sendToGemini': false,
        'attachmentPath': null,
        'attachmentType': null,
        'fileName': null,
      });
    });

    _scrollToBottom();
  }

  // ============================================================
  // SCROLL
  // ============================================================

  void _scrollToBottom() {
    Future.delayed(const Duration(milliseconds: 100), () {
      if (!_scrollController.hasClients) {
        return;
      }

      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
      );
    });
  }

  // ============================================================
  // TIME
  // ============================================================

  String _formatTime(DateTime dateTime) {
    int hour = dateTime.hour;

    final String minute = dateTime.minute.toString().padLeft(2, '0');

    final String period = hour >= 12 ? 'PM' : 'AM';

    if (hour == 0) {
      hour = 12;
    } else if (hour > 12) {
      hour -= 12;
    }

    return '$hour:$minute $period';
  }

  void _showSnackBar(String text) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final List<String> quickReplies = _getQuickReplies();

    return Scaffold(
      backgroundColor: chatBackground,

      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        titleSpacing: 12,

        title: const Row(
          children: [
            CircleAvatar(
              radius: 20,
              backgroundColor: lightBeige,
              child: Icon(Icons.support_agent_rounded, color: darkMaroon),
            ),

            SizedBox(width: 11),

            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Lumear AI Support',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),

                SizedBox(height: 2),

                Text(
                  'Online • AI Assistant',
                  style: TextStyle(fontSize: 10.5, color: lightBeige),
                ),
              ],
            ),
          ],
        ),

        actions: [
          IconButton(
            tooltip: 'Restart conversation',
            onPressed: _resetConversation,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),

      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
            color: const Color(0xFFF0E6E1),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline_rounded, size: 17, color: maroon),

                SizedBox(width: 7),

                Expanded(
                  child: Text(
                    'Lumear AI can help with app usage, '
                    'deliveries, screenshots and supported '
                    'documents.',
                    style: TextStyle(fontSize: 11, height: 1.4, color: maroon),
                  ),
                ),
              ],
            ),
          ),

          // ====================================================
          // MESSAGES
          // ====================================================
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.fromLTRB(14, 18, 14, 12),
              itemCount: _messages.length + (_isBotTyping ? 1 : 0),
              itemBuilder: (context, index) {
                if (_isBotTyping && index == _messages.length) {
                  return _typingBubble();
                }

                final message = _messages[index];

                return _messageBubble(
                  message: message['message']?.toString() ?? '',
                  isRider: message['isRider'] == true,
                  time: message['time'] as DateTime,
                  attachmentPath: message['attachmentPath']?.toString(),
                  attachmentType: message['attachmentType']?.toString(),
                  fileName: message['fileName']?.toString(),
                );
              },
            ),
          ),

          // ====================================================
          // QUICK REPLIES
          // ====================================================
          if (!_isBotTyping && quickReplies.isNotEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 7),
              color: Colors.white,
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: Row(
                  children: quickReplies.map((reply) {
                    return Padding(
                      padding: const EdgeInsets.only(right: 7),
                      child: OutlinedButton(
                        onPressed: () => _selectQuickReply(reply),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: darkMaroon,
                          side: const BorderSide(color: borderColor),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(20),
                          ),
                        ),
                        child: Text(
                          reply,
                          style: const TextStyle(fontSize: 11.5),
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),

          // ====================================================
          // PENDING ATTACHMENT PREVIEW
          // ====================================================
          if (_pendingAttachmentPath != null) _pendingAttachmentPreview(),

          // ====================================================
          // INPUT
          // ====================================================
          Container(
            padding: const EdgeInsets.fromLTRB(8, 8, 10, 10),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: borderColor)),
            ),
            child: SafeArea(
              top: false,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  IconButton(
                    onPressed: _isBotTyping ? null : _showAttachmentMenu,
                    icon: const Icon(Icons.add_circle_outline_rounded),
                    color: darkMaroon,
                    iconSize: 28,
                  ),

                  Expanded(
                    child: Container(
                      constraints: const BoxConstraints(
                        minHeight: 46,
                        maxHeight: 115,
                      ),
                      decoration: BoxDecoration(
                        color: inputBackground,
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: borderColor),
                      ),
                      child: TextField(
                        controller: _messageController,
                        enabled: !_isBotTyping,
                        minLines: 1,
                        maxLines: 4,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(
                          hintText: 'Message Lumear Support',
                          border: InputBorder.none,
                          contentPadding: EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 13,
                          ),
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(width: 7),

                  SizedBox(
                    width: 46,
                    height: 46,
                    child: ElevatedButton(
                      onPressed: _isBotTyping ? null : _sendMessage,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: darkMaroon,
                        foregroundColor: Colors.white,
                        padding: EdgeInsets.zero,
                        shape: const CircleBorder(),
                      ),
                      child: _isBotTyping
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.arrow_upward_rounded),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PENDING ATTACHMENT PREVIEW
  // ============================================================

  Widget _pendingAttachmentPreview() {
    final bool isImage = _pendingAttachmentType == 'image';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
      color: Colors.white,
      child: Align(
        alignment: Alignment.centerLeft,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              constraints: const BoxConstraints(maxWidth: 230),
              padding: const EdgeInsets.all(7),
              decoration: BoxDecoration(
                color: inputBackground,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: borderColor),
              ),
              child: isImage
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Image.file(
                        File(_pendingAttachmentPath!),
                        width: 150,
                        height: 105,
                        fit: BoxFit.cover,
                      ),
                    )
                  : Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.description_rounded,
                          color: darkMaroon,
                          size: 32,
                        ),

                        const SizedBox(width: 9),

                        Flexible(
                          child: Text(
                            _pendingAttachmentName ?? 'Document',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: darkText,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
            ),

            Positioned(
              right: -8,
              top: -8,
              child: GestureDetector(
                onTap: _removePendingAttachment,
                child: Container(
                  width: 25,
                  height: 25,
                  decoration: const BoxDecoration(
                    color: darkMaroon,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.close_rounded,
                    color: Colors.white,
                    size: 16,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // MESSAGE BUBBLE
  // ============================================================

  Widget _messageBubble({
    required String message,
    required bool isRider,
    required DateTime time,
    String? attachmentPath,
    String? attachmentType,
    String? fileName,
  }) {
    final double maxWidth = MediaQuery.sizeOf(context).width * 0.76;

    final bool hasAttachment =
        attachmentPath != null && attachmentPath.isNotEmpty;

    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Align(
        alignment: isRider ? Alignment.centerRight : Alignment.centerLeft,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: IntrinsicWidth(
            child: Container(
              padding: hasAttachment
                  ? const EdgeInsets.all(6)
                  : const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isRider ? darkMaroon : Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: isRider ? null : Border.all(color: borderColor),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (hasAttachment && attachmentType == 'image')
                    ClipRRect(
                      borderRadius: BorderRadius.circular(13),
                      child: Image.file(
                        File(attachmentPath),
                        width: 235,
                        height: 180,
                        fit: BoxFit.cover,
                      ),
                    ),

                  if (hasAttachment && attachmentType != 'image')
                    Container(
                      constraints: const BoxConstraints(
                        minWidth: 180,
                        maxWidth: 235,
                      ),
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: isRider
                            ? Colors.white.withValues(alpha: 0.12)
                            : cream,
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.description_rounded,
                            color: isRider ? Colors.white : darkMaroon,
                          ),

                          const SizedBox(width: 8),

                          Expanded(
                            child: Text(
                              fileName ?? 'Document',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: isRider ? Colors.white : darkText,
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                  if (message.trim().isNotEmpty) ...[
                    if (hasAttachment) const SizedBox(height: 7),

                    Text(
                      message,
                      style: TextStyle(
                        color: isRider ? Colors.white : darkText,
                        fontSize: 14,
                        height: 1.4,
                      ),
                    ),
                  ],

                  const SizedBox(height: 4),

                  Align(
                    alignment: Alignment.bottomRight,
                    widthFactor: 1,
                    child: Text(
                      _formatTime(time),
                      style: TextStyle(
                        fontSize: 8.5,
                        color: isRider ? Colors.white60 : mutedText,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // TYPING BUBBLE
  // ============================================================

  Widget _typingBubble() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: borderColor),
          ),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 15,
                height: 15,
                child: CircularProgressIndicator(strokeWidth: 2, color: maroon),
              ),

              SizedBox(width: 8),

              Text(
                'Lumear AI is typing...',
                style: TextStyle(color: brownBeige, fontSize: 11.5),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
