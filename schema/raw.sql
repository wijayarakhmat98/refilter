--
-- PostgreSQL database dump
--

-- Dumped from database version 15.3
-- Dumped by pg_dump version 15.3

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: raw; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.raw (
    auto_id integer NOT NULL,
    "timestamp" timestamp without time zone DEFAULT now() NOT NULL,
    website text NOT NULL,
    type text,
    id integer NOT NULL,
    content text NOT NULL
);


--
-- Name: raw_auto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.raw ALTER COLUMN auto_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.raw_auto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: raw raw_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raw
    ADD CONSTRAINT raw_pkey PRIMARY KEY (auto_id);


--
-- Name: raw_website_type_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX raw_website_type_id_idx ON public.raw USING btree (website, type, id) WITH (deduplicate_items='true');


--
-- Name: raw_website_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX raw_website_type_idx ON public.raw USING btree (website, type) WITH (deduplicate_items='true');


--
-- PostgreSQL database dump complete
--
