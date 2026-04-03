# Implementation Plan: Mail Indexer and Searcher

## Overview

This implementation plan breaks down the mail indexer and searcher feature into discrete coding tasks. The feature enables Laravel users to connect IMAP email accounts, automatically index emails into MySQL and Typesense, and perform fast full-text searches through a Livewire-based interface.

The implementation follows this sequence:
1. Database schema and models
2. Core services (IMAP connection, indexing, search)
3. Background jobs (sync and indexing)
4. Livewire UI components
5. Routes and integration
6. Testing

## Tasks

- [x] 1. Set up database schema and migrations
  - Create migration for `imap_settings` table with user relationship, encrypted password field, and indexes
  - Create migration for `emails` table with user relationship, message metadata fields, JSON fields for addresses/attachments, and indexes
  - _Requirements: 1.4, 3.6, 3.8, 6.1_

- [x] 2. Implement core models
  - [x] 2.1 Create ImapSetting model with encryption
    - Implement model with fillable fields, casts, and hidden password field
    - Add password encryption mutator and decryption accessor
    - Define user relationship
    - _Requirements: 1.4, 6.1_
  
  - [x]* 2.2 Write property test for password encryption
    - **Property 1: Password Encryption Round-Trip**
    - **Validates: Requirements 1.6, 6.1**
  
  - [x] 2.3 Create Email model with Scout integration
    - Implement model with fillable fields, casts for JSON and datetime
    - Add Searchable trait and configure toSearchableArray method
    - Define user relationship
    - _Requirements: 3.6, 3.7, 3.8, 4.2_
  
  - [x]* 2.4 Write unit tests for Email model
    - Test searchable array generation
    - Test relationship definitions
    - Test JSON field casting
    - _Requirements: 3.6, 3.7_

- [x] 3. Create data transfer objects
  - [x] 3.1 Create EmailData DTO
    - Define class with constructor for message ID, addresses, subject, date, body content, and attachment metadata
    - _Requirements: 3.2, 3.3, 3.4_
  
  - [x] 3.2 Create ImapConnection DTO
    - Define class with IMAP resource and settings
    - Add disconnect method
    - _Requirements: 2.3_

- [x] 4. Implement IMAP connection service
  - [x] 4.1 Create ImapConnectionService interface and implementation
    - Implement testConnection method with timeout and error handling
    - Implement connect method using ImapSetting model
    - Implement getFolders method to retrieve all IMAP folders
    - Implement getMessageIds method to list messages in a folder
    - Implement getEmail method to retrieve full email content
    - Use webklex/php-imap package or PHP IMAP extension
    - _Requirements: 1.3, 2.3, 2.4, 3.1, 9.1_
  
  - [x]* 4.2 Write unit tests for IMAP connection service
    - Test connection with valid credentials using mock IMAP server
    - Test connection failure scenarios (timeout, auth failure, SSL errors)
    - Test folder retrieval
    - Test message ID retrieval
    - _Requirements: 1.3, 1.5, 8.1_

- [x] 5. Implement email indexing service
  - [x] 5.1 Create EmailIndexingService interface and implementation
    - Implement isEmailIndexed method to check for existing records
    - Implement extractEmailData method to parse IMAP message into EmailData DTO
    - Extract sender, recipients, subject, date, body (text and HTML), and attachment metadata
    - Ensure attachment file content is NOT downloaded
    - Implement indexEmail method to create Email record with transaction
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.9, 6.5_
  
  - [x]* 5.2 Write property test for email data extraction
    - **Property 3: Email Data Extraction Completeness**
    - **Validates: Requirements 3.2, 3.3, 3.4, 3.5, 6.5**
  
  - [x]* 5.3 Write property test for indexing idempotence
    - **Property 4: Indexing Idempotence**
    - **Validates: Requirements 3.9**
  
  - [x]* 5.4 Write unit tests for indexing service
    - Test extraction with various email formats (plain text, HTML, multipart)
    - Test attachment metadata extraction
    - Test error handling for malformed emails
    - _Requirements: 3.2, 3.3, 3.4, 3.10_

- [x] 6. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Implement background jobs
  - [x] 7.1 Create SyncEmailsJob
    - Implement handle method to iterate through users with active IMAP settings
    - For each user, connect to IMAP and retrieve folders
    - For each folder, identify unindexed emails by comparing IMAP message IDs with database
    - Queue IndexEmailJob for each unindexed email
    - Add error handling to continue processing other users on failure
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 8.1, 9.1_
  
  - [x]* 7.2 Write property test for unindexed email identification
    - **Property 2: Unindexed Email Identification**
    - **Validates: Requirements 2.5**
  
  - [x] 7.3 Create IndexEmailJob with retry logic
    - Implement handle method to retrieve email from IMAP and index using EmailIndexingService
    - Configure 3 retries with exponential backoff (60s, 120s, 240s)
    - Implement failed method to mark email with indexing_failed_at and indexing_error
    - Use database transaction to ensure MySQL and Typesense consistency
    - _Requirements: 3.1, 3.6, 3.7, 8.2, 8.3, 8.5_
  
  - [x]* 7.4 Write unit tests for jobs
    - Test SyncEmailsJob user iteration and job queuing
    - Test IndexEmailJob retry logic and failure handling
    - Test error handling for IMAP connection failures
    - _Requirements: 8.1, 8.2, 8.3_

- [x] 8. Configure Laravel scheduler
  - [x] 8.1 Add SyncEmailsJob to scheduler
    - Register job in routes/console.php
    - Configure appropriate schedule (e.g., every 15 minutes)
    - _Requirements: 2.1_

- [x] 9. Implement email search service
  - [x] 9.1 Create EmailSearchService interface and implementation
    - Implement search method using Scout with user_id filter and folder filter
    - Implement getRecent method for chronological display when no query
    - Implement formatResult method to generate display string with from, subject, and preview
    - Implement highlighting logic for search terms in results
    - Add fallback to MySQL LIKE queries when Typesense unavailable
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 6.2, 8.4, 9.3_
  
  - [x]* 9.2 Write property test for search result data isolation
    - **Property 5: Search Result Data Isolation**
    - **Validates: Requirements 4.3, 6.2, 6.4**
  
  - [x]* 9.3 Write property test for search result formatting
    - **Property 6: Search Result Formatting**
    - **Validates: Requirements 4.5**
  
  - [x]* 9.4 Write property test for search term highlighting
    - **Property 7: Search Term Highlighting**
    - **Validates: Requirements 4.6**
  
  - [x]* 9.5 Write property test for folder filtering
    - **Property 10: Folder Filtering Accuracy**
    - **Validates: Requirements 9.3**
  
  - [x]* 9.6 Write unit tests for search service
    - Test search with various queries
    - Test recent emails retrieval
    - Test result formatting
    - Test Typesense unavailability fallback
    - _Requirements: 4.2, 4.7, 7.1, 8.4_

- [ ] 10. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 11. Implement Livewire components
  - [ ] 11.1 Create ImapSettingsComponent
    - Define public properties for form fields (hostname, port, username, password, encryption)
    - Implement validation rules
    - Implement mount method to load existing settings
    - Implement testConnection method using ImapConnectionService
    - Implement save method to store encrypted settings
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 10.1, 10.4, 10.6_
  
  - [ ]* 11.2 Write property test for input validation
    - **Property 9: Input Validation Completeness**
    - **Validates: Requirements 8.6**
  
  - [ ]* 11.3 Write unit tests for ImapSettingsComponent
    - Test form validation
    - Test connection testing
    - Test save functionality
    - Test error message display
    - _Requirements: 1.3, 1.5, 1.7_
  
  - [ ] 11.4 Create EmailSearchComponent with pagination
    - Define public properties for query, folderFilter, selectedEmailId
    - Implement updatedQuery method with debouncing
    - Implement render method to call EmailSearchService
    - Implement selectEmail and closeModal methods
    - Add WithPagination trait for result pagination
    - _Requirements: 4.1, 4.2, 4.3, 4.5, 4.6, 4.7, 4.8, 7.5, 10.2, 10.4, 10.5_
  
  - [ ]* 11.5 Write unit tests for EmailSearchComponent
    - Test search query updates
    - Test pagination
    - Test modal opening
    - Test debouncing behavior
    - _Requirements: 4.8, 7.5, 10.5_
  
  - [ ] 11.6 Create EmailModalComponent
    - Define public properties for email and show state
    - Implement open method with authorization check
    - Implement close method
    - Implement openInNewTab method
    - Add HTML sanitization for email body rendering
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 6.4, 6.6, 9.4, 10.3, 10.4_
  
  - [ ]* 11.7 Write property test for HTML sanitization
    - **Property 8: HTML Sanitization Safety**
    - **Validates: Requirements 5.3, 6.6**
  
  - [ ]* 11.8 Write unit tests for EmailModalComponent
    - Test email display
    - Test authorization checks
    - Test HTML sanitization
    - Test attachment metadata display
    - _Requirements: 5.2, 5.8, 6.4, 6.6_

- [ ] 12. Create Blade views for Livewire components
  - [ ] 12.1 Create IMAP settings view
    - Create form with Flux UI components for hostname, port, username, password, encryption
    - Add test connection button with loading state
    - Add save button with validation error display
    - _Requirements: 1.1, 1.2, 1.7, 10.1, 10.6_
  
  - [ ] 12.2 Create email search view
    - Create search input with debouncing
    - Create results list with formatted display (from, subject, preview)
    - Add folder filter dropdown
    - Add pagination controls
    - Highlight search terms in results
    - _Requirements: 4.1, 4.5, 4.6, 4.8, 9.3, 10.2, 10.5_
  
  - [ ] 12.3 Create email modal view
    - Create modal with email header (from, to, subject, date, folder)
    - Display email body with safe HTML rendering
    - Add attachment metadata list
    - Add close button and open in new tab button
    - _Requirements: 5.1, 5.2, 5.4, 5.5, 5.6, 5.7, 5.8, 9.4, 10.3_

- [ ] 13. Configure routes and authentication
  - [ ] 13.1 Add routes for IMAP settings page
    - Create route for settings page with auth middleware
    - _Requirements: 1.1, 6.3_
  
  - [ ] 13.2 Add routes for email search page
    - Create route for search page with auth middleware
    - Create route for email detail page (new tab view) with auth middleware
    - _Requirements: 4.1, 5.7, 6.3_

- [ ] 14. Configure Typesense and Scout
  - [ ] 14.1 Set up Typesense schema
    - Create artisan command to initialize Typesense collection with schema from design
    - Configure Scout in config/scout.php
    - _Requirements: 3.7, 4.2_
  
  - [ ] 14.2 Configure Scout model settings
    - Ensure Email model searchableAs returns 'emails'
    - Verify toSearchableArray includes user_id for filtering
    - _Requirements: 3.7, 4.3, 6.2_

- [ ] 15. Implement performance optimizations
  - [ ] 15.1 Add search input debouncing
    - Configure Livewire wire:model.debounce on search input (300ms)
    - _Requirements: 7.5_
  
  - [ ] 15.2 Configure queue workers
    - Document queue worker setup in README
    - Configure queue connection in .env.example
    - _Requirements: 7.2, 7.3_
  
  - [ ] 15.3 Add batch indexing support
    - Configure Scout to use batch indexing when available
    - _Requirements: 7.4_

- [ ] 16. Add artisan commands for maintenance
  - [ ] 16.1 Create command to retry failed indexing
    - Create artisan command to requeue emails with indexing_failed_at set
    - _Requirements: 8.3_
  
  - [ ] 16.2 Create command to clean up inconsistencies
    - Create artisan command to identify and fix orphaned records
    - _Requirements: Error Handling - Data Consistency_

- [ ] 17. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ]* 18. Write integration tests
  - [ ]* 18.1 Write IMAP integration tests
    - Test connection to mock IMAP server
    - Test folder and email retrieval
    - Test authentication failure handling
    - _Requirements: 1.3, 2.3, 2.4, 3.1_
  
  - [ ]* 18.2 Write Typesense integration tests
    - Test indexing via Scout
    - Test search query execution with user filter
    - Test unavailability handling
    - _Requirements: 3.7, 4.2, 4.3, 8.4_
  
  - [ ]* 18.3 Write database integration tests
    - Test email storage with transactions
    - Test data isolation queries
    - _Requirements: 3.6, 6.2, 8.5_

- [ ]* 19. Write end-to-end tests
  - [ ]* 19.1 Test settings configuration flow
    - User enters IMAP settings → tests connection → saves settings
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.7_
  
  - [ ]* 19.2 Test email synchronization flow
    - Trigger sync job → verify emails indexed → verify searchable
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.6, 3.7_
  
  - [ ]* 19.3 Test search flow
    - User enters query → results display → clicks email → modal opens
    - _Requirements: 4.1, 4.2, 4.5, 5.1, 5.2_
  
  - [ ]* 19.4 Test error recovery flow
    - IMAP connection fails → error displayed → user corrects → retry succeeds
    - _Requirements: 1.5, 8.1_

- [ ] 20. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- Integration and E2E tests verify complete workflows
- The implementation uses Laravel 11.x, Livewire 3.x, MySQL 8.0+, and Typesense 26.0+
- Queue workers must be running for background synchronization to work
- Typesense must be configured and running before indexing can begin
