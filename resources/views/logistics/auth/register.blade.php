@extends('layouts.logistics-auth')


@section('title', 'Register Sorting Center — Lumear')


@section('content')


<div class="registration-layout">


    {{-- =====================================================
         REGISTRATION HEADING
         ===================================================== --}}

    <div class="registration-heading">

        <div>

            <span class="section-eyebrow">
                LOGISTICS PARTNER APPLICATION
            </span>

            <h1>
                Register your
                <span>sorting center.</span>
            </h1>

            <p>
                Provide your representative and business information.
                Your application will be reviewed by the Lumear administrator.
            </p>

        </div>


        <div class="registration-progress">

            <div class="progress-number">
                01
            </div>

            <div>

                <strong>
                    Application
                </strong>

                <span>
                    Administrator approval required
                </span>

            </div>

        </div>

    </div>



    {{-- =====================================================
         REGISTRATION FORM
         ===================================================== --}}

    <form
        id="logisticsRegistrationForm"
        class="registration-form"
        data-pending-url="{{ route('logistics.pending') }}"
    >


        {{-- =================================================
             SECTION 1
             REPRESENTATIVE INFORMATION
             ================================================= --}}

        <section class="registration-section">


            <div class="section-title">

                <div class="section-number">
                    01
                </div>

                <div>

                    <h2>
                        Representative Information
                    </h2>

                    <p>
                        Information of the person managing the logistics account.
                    </p>

                </div>

            </div>



            <div class="form-grid three-columns">


                {{-- Last Name --}}

                <div class="form-group">

                    <label for="lastName">
                        Last name <b>*</b>
                    </label>

                    <input
                        type="text"
                        id="lastName"
                        name="last_name"
                        placeholder="Last name"
                        required
                    >

                </div>



                {{-- First Name --}}

                <div class="form-group">

                    <label for="firstName">
                        First name <b>*</b>
                    </label>

                    <input
                        type="text"
                        id="firstName"
                        name="first_name"
                        placeholder="First name"
                        required
                    >

                </div>



                {{-- Middle Initial --}}

                <div class="form-group">

                    <label for="middleInitial">
                        Middle initial
                    </label>

                    <input
                        type="text"
                        id="middleInitial"
                        name="middle_initial"
                        maxlength="2"
                        placeholder="M.I."
                    >

                </div>


            </div>



            <div class="form-grid two-columns">


                {{-- Sex --}}

                <div class="form-group">

                    <label for="sex">
                        Sex <b>*</b>
                    </label>

                    <select
                        id="sex"
                        name="sex"
                        required
                    >

                        <option value="">
                            Select sex
                        </option>

                        <option value="Male">
                            Male
                        </option>

                        <option value="Female">
                            Female
                        </option>

                    </select>

                </div>



                {{-- Birthday --}}

                <div class="form-group">

                    <label for="birthday">
                        Birthday <b>*</b>
                    </label>

                    <input
                        type="date"
                        id="birthday"
                        name="birthday"
                        required
                    >

                </div>


            </div>



            <div class="form-grid two-columns">


                {{-- Automatic Age --}}

                <div class="form-group">

                    <label for="age">
                        Age <b>*</b>
                    </label>

                    <div class="age-input">

                        <input
                            type="text"
                            id="age"
                            name="age"
                            placeholder="Automatically calculated"
                            readonly
                            required
                        >

                        <span>
                            AUTO
                        </span>

                    </div>

                </div>



                {{-- Contact Number --}}

                <div class="form-group">

                    <label for="contactNumber">
                        Contact No. <b>*</b>
                    </label>

                    <input
                        type="tel"
                        id="contactNumber"
                        name="contact_number"
                        placeholder="09XXXXXXXXX"
                        required
                    >

                </div>


            </div>



            {{-- Email --}}

            <div class="form-group">

                <label for="registrationEmail">
                    E-mail <b>*</b>
                </label>

                <input
                    type="email"
                    id="registrationEmail"
                    name="email"
                    placeholder="name@business.com"
                    required
                >

            </div>


        </section>



        {{-- =================================================
             SECTION 2
             SORTING CENTER ADDRESS
             ================================================= --}}

        <section class="registration-section">


            <div class="section-title">

                <div class="section-number">
                    02
                </div>

                <div>

                    <h2>
                        Sorting Center Address
                    </h2>

                    <p>
                        Select your province, municipality or city,
                        barangay, and complete street address.
                    </p>

                </div>

            </div>



            <div class="form-grid three-columns">


                {{-- =========================================
                     PROVINCE

                     Automatically populated by PSGC API.
                     ========================================= --}}

                <div class="form-group">

                    <label for="province">
                        Province <b>*</b>
                    </label>

                    <select
                        id="province"
                        name="province_code"
                        required
                    >

                        <option value="">
                            Loading provinces...
                        </option>

                    </select>

                </div>



                {{-- =========================================
                     MUNICIPALITY / CITY

                     Automatically changes depending on
                     selected province.
                     ========================================= --}}

                <div class="form-group">

                    <label for="municipality">
                        Municipality / City <b>*</b>
                    </label>

                    <select
                        id="municipality"
                        name="municipality_code"
                        required
                        disabled
                    >

                        <option value="">
                            Select province first
                        </option>

                    </select>

                </div>



                {{-- =========================================
                     BARANGAY

                     Automatically changes depending on
                     selected municipality/city.
                     ========================================= --}}

                <div class="form-group">

                    <label for="barangay">
                        Barangay <b>*</b>
                    </label>

                    <select
                        id="barangay"
                        name="barangay_code"
                        required
                        disabled
                    >

                        <option value="">
                            Select municipality first
                        </option>

                    </select>

                </div>


            </div>



            {{-- Street / House Number --}}

            <div class="form-group">

                <label for="streetAddress">
                    Street / House No. / Building <b>*</b>
                </label>

                <input
                    type="text"
                    id="streetAddress"
                    name="street_address"
                    placeholder="House number, street, subdivision, building, etc."
                    required
                >

            </div>



            {{-- API information --}}

            <div class="api-notice">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path d="M12 11v5"/>

                    <path d="M12 8h.01"/>

                </svg>


                <span>
                    Philippine address data is automatically loaded
                    using PSGC geographic data. Select a province first
                    to load its cities and municipalities, then select
                    a municipality or city to load its barangays.
                </span>

            </div>


        </section>



        {{-- =================================================
             SECTION 3
             BUSINESS INFORMATION
             ================================================= --}}

        <section class="registration-section">


            <div class="section-title">

                <div class="section-number">
                    03
                </div>

                <div>

                    <h2>
                        Business Information
                    </h2>

                    <p>
                        Information used by Lumear to verify your sorting center.
                    </p>

                </div>

            </div>



            {{-- Business Name --}}

            <div class="form-group">

                <label for="businessName">
                    Business name
                </label>

                <input
                    type="text"
                    id="businessName"
                    name="business_name"
                    placeholder="Registered business or sorting center name"
                >

            </div>



            {{-- Upload Documents --}}

            <div class="upload-grid">


                {{-- VALID ID --}}

                <label
                    class="upload-box"
                    for="validId"
                >

                    <input
                        type="file"
                        id="validId"
                        name="valid_id"
                        accept=".jpg,.jpeg,.png,.pdf"
                        required
                    >


                    <div class="upload-icon">

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path d="M12 16V4"/>

                            <path d="m7 9 5-5 5 5"/>

                            <path d="M5 20h14"/>

                        </svg>

                    </div>


                    <strong>
                        Upload Valid ID *
                    </strong>


                    <span class="file-name">
                        JPG, PNG or PDF
                    </span>

                </label>



                {{-- BUSINESS / DTI PERMIT --}}

                <label
                    class="upload-box"
                    for="businessPermit"
                >

                    <input
                        type="file"
                        id="businessPermit"
                        name="business_permit"
                        accept=".jpg,.jpeg,.png,.pdf"
                        required
                    >


                    <div class="upload-icon">

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path d="M12 16V4"/>

                            <path d="m7 9 5-5 5 5"/>

                            <path d="M5 20h14"/>

                        </svg>

                    </div>


                    <strong>
                        Business / DTI Permit *
                    </strong>


                    <span class="file-name">
                        JPG, PNG or PDF
                    </span>

                </label>


            </div>


        </section>



        {{-- =================================================
             SUBMISSION
             ================================================= --}}

        <section class="registration-submit">


            <div class="approval-notice">


                <div class="approval-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >

                        <path
                            d="M12 3 4 6v5c0 5 3.4 8.8 8 10 4.6-1.2 8-5 8-10V6l-8-3Z"
                        />

                        <path d="M12 8v4"/>

                        <path d="M12 16h.01"/>

                    </svg>

                </div>



                <div>

                    <strong>
                        Administrator approval required
                    </strong>


                    <p>
                        After submitting your registration, please wait
                        for the administrator's approval. The result will
                        be sent to your registered email address.
                    </p>

                </div>


            </div>



            <div class="registration-actions">


                <a
                    href="{{ route('logistics.login') }}"
                    class="cancel-button"
                >
                    Cancel
                </a>



                <button
                    type="submit"
                    class="submit-application-button"
                    id="registrationSubmitButton"
                >

                    Submit Application


                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path d="M5 12h14"/>

                        <path d="m13 6 6 6-6 6"/>

                    </svg>

                </button>


            </div>


        </section>


    </form>


</div>


@endsection