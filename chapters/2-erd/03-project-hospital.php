<?php
return [
    'title'   => 'Project: Hospital',
    'summary' => 'Departments and doctors, patients and appointments, prescriptions, heads of department. Four steps.',
    'intro'   => <<<'HTML'
<h3>The case</h3>
<p>A hospital is divided into <strong>departments</strong> (cardiology, pediatrics, ...), each with a name and a floor.
A <strong>doctor</strong> has a first name, a last name and a specialty, and works in one department.</p>
<p><strong>Patients</strong> are registered with first name, last name, birth date and phone number. A patient makes
<strong>appointments</strong>: one appointment is with one doctor, at a certain day and time, for a reason that the patient describes.</p>
<p>During an appointment the doctor can prescribe <strong>medicines</strong>. A medicine has a name and a unit (tablet, ml, ...).
One appointment can lead to several medicines, and a medicine is of course prescribed at many appointments. For each
prescription the dosage (a short text such as <code>2 per day</code>) and the number of days are kept.</p>
<p>Every department has one <strong>head</strong>, who is a doctor. A doctor is head of at most one department.</p>

<h3>How a project works</h3>
<p>A project is one case that you design in <strong>four steps</strong>. Every step adds a part of the story to the diagram.
A step starts from the model design of the step before it, so a mistake in step 1 does not follow you to step 4.
Read the whole case first: a good designer knows where the story is going.</p>
<p>The names to use are given in every step. Give every attribute a data type, mark the primary keys, and draw the relationships
from the "one" side to the "many" side.</p>
HTML,
    'exercises' => [
        [
            'type'     => 'erd',
            'title'    => 'Step 1: Departments and doctors',
            'task'     => '<p>A department has a name and a floor number. A doctor has a first name, a last name and a specialty, and works in one department.</p><p>Draw <code>Departments</code> (<code>DepartmentID</code>, <code>Name</code>, <code>Floor</code>) and <code>Doctors</code> (<code>DoctorID</code>, <code>FirstName</code>, <code>LastName</code>, <code>Specialty</code>) and their relationship. The foreign key is called <code>DepartmentID</code>.</p>',
            'hint'     => 'One department has many doctors: the foreign key goes in <code>Doctors</code>.',
            'solution' => 'Departments: DepartmentID int PK, Name varchar, Floor int
Doctors: DoctorID int PK, FirstName varchar, LastName varchar, Specialty varchar, DepartmentID int > Departments',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 2: Patients and appointments',
            'task'     => '<p>Add the patients (first name, last name, birth date, phone number) and their appointments. An appointment is for one patient, with one doctor, starts at a day and time, and has a reason (a longer text).</p><p>Add <code>Patients</code> (<code>PatientID</code>, <code>FirstName</code>, <code>LastName</code>, <code>BirthDate</code>, <code>Phone</code>) and <code>Appointments</code> (<code>AppointmentID</code>, <code>StartsAt</code>, <code>Reason</code>) with two relationships. Foreign keys are named after the key they point to.</p>',
            'hint'     => '<code>Appointments</code> is on the many side of both relationships and gets <code>PatientID</code> and <code>DoctorID</code>. A birth date is a <code>DATE</code>, the start of an appointment a <code>DATETIME</code>.',
            'starter'  => 'Departments: DepartmentID int PK, Name varchar, Floor int
Doctors: DoctorID int PK, FirstName varchar, LastName varchar, Specialty varchar, DepartmentID int > Departments',
            'solution' => 'Departments: DepartmentID int PK, Name varchar, Floor int
+ Doctors: DoctorID int PK, FirstName varchar, LastName varchar, Specialty varchar, DepartmentID int > Departments
Appointments: AppointmentID int PK, StartsAt datetime, Reason text, PatientID int > Patients, DoctorID int > Doctors
Patients: PatientID int PK, FirstName varchar, LastName varchar, BirthDate date, Phone varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 3: Prescriptions',
            'task'     => '<p>At an appointment the doctor can prescribe medicines. A medicine has a name and a unit such as <code>tablet</code>. An appointment can lead to several medicines and a medicine is prescribed at many appointments. Per prescription we keep the dosage (a short text) and the number of days. A medicine is prescribed at most once per appointment.</p><p>Add <code>Medicines</code> (<code>MedicineID</code>, <code>Name</code>, <code>Unit</code>) and the link table <code>Prescriptions</code> with a composite key, <code>Dosage</code> and <code>Days</code>.</p>',
            'hint'     => '<code>Prescriptions</code> sits between <code>Appointments</code> and <code>Medicines</code>: two foreign keys that are together the primary key, plus the two attributes of the prescription itself.',
            'starter'  => 'Departments: DepartmentID int PK, Name varchar, Floor int
+ Doctors: DoctorID int PK, FirstName varchar, LastName varchar, Specialty varchar, DepartmentID int > Departments
Appointments: AppointmentID int PK, StartsAt datetime, Reason text, PatientID int > Patients, DoctorID int > Doctors
Patients: PatientID int PK, FirstName varchar, LastName varchar, BirthDate date, Phone varchar',
            'solution' => 'Departments: DepartmentID int PK, Name varchar, Floor int
+ Doctors: DoctorID int PK, FirstName varchar, LastName varchar, Specialty varchar, DepartmentID int > Departments
Appointments: AppointmentID int PK, StartsAt datetime, Reason text, PatientID int > Patients, DoctorID int > Doctors
+ Prescriptions: AppointmentID int PK > Appointments, MedicineID int PK > Medicines, Dosage varchar, Days int
Patients: PatientID int PK, FirstName varchar, LastName varchar, BirthDate date, Phone varchar
+ Medicines: MedicineID int PK, Name varchar, Unit varchar',
        ],
        [
            'type'     => 'erd',
            'title'    => 'Step 4: The head of a department',
            'task'     => '<p>Every department has one head, who is a doctor. A doctor is head of at most one department.</p><p>Add this second relationship between <code>Doctors</code> and <code>Departments</code>: a foreign key <code>HeadDoctorID</code> in <code>Departments</code>, one-to-one.</p>',
            'hint'     => 'Drag from <code>Doctors</code> to <code>Departments</code>, rename the new attribute to <code>HeadDoctorID</code>, and click the crow\'s foot at <code>Departments</code> so that both ends are a bar.',
            'starter'  => 'Departments: DepartmentID int PK, Name varchar, Floor int
+ Doctors: DoctorID int PK, FirstName varchar, LastName varchar, Specialty varchar, DepartmentID int > Departments
Appointments: AppointmentID int PK, StartsAt datetime, Reason text, PatientID int > Patients, DoctorID int > Doctors
+ Prescriptions: AppointmentID int PK > Appointments, MedicineID int PK > Medicines, Dosage varchar, Days int
Patients: PatientID int PK, FirstName varchar, LastName varchar, BirthDate date, Phone varchar
+ Medicines: MedicineID int PK, Name varchar, Unit varchar',
            'solution' => 'Departments: DepartmentID int PK, Name varchar, Floor int, HeadDoctorID int > Doctors UNIQUE
+ Doctors: DoctorID int PK, FirstName varchar, LastName varchar, Specialty varchar, DepartmentID int > Departments
Appointments: AppointmentID int PK, StartsAt datetime, Reason text, PatientID int > Patients, DoctorID int > Doctors
+ Prescriptions: AppointmentID int PK > Appointments, MedicineID int PK > Medicines, Dosage varchar, Days int
Patients: PatientID int PK, FirstName varchar, LastName varchar, BirthDate date, Phone varchar
+ Medicines: MedicineID int PK, Name varchar, Unit varchar',
        ],
    ],
];
